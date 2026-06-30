#!/bin/bash
set -Eeuo pipefail

# Deploy latest release tag (safe, reproducible) or latest main commit (--current).
# Default mode:
# - If last_release is a tag: deploy only if a newer tag exists than that tag's commit
# - If last_release is a commit: deploy only if latest tag commit is newer than that commit
# Supports empty starting point, --force, and --current.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WORKDIR="$(cd "$SCRIPT_DIR/.." && pwd)"
RELEASE_FILE="$WORKDIR/storage/app/last_release"
DEPLOY_LOCK_PATH="$WORKDIR/storage/app/deploy.lock"

REPO_URL="${REPO_URL:-https://www.github.com/STEMMechanics/website.git}"

# Optional Pushover notification configuration
PUSHOVER_TOKEN="${PUSHOVER_TOKEN:-}"
PUSHOVER_USER="${PUSHOVER_USER:-}"
PUSHOVER_TITLE="${PUSHOVER_TITLE:-Server Update}"

FORCE=0
CURRENT=0
for arg in "$@"; do
  case "$arg" in
    -force|--force) FORCE=1 ;;
    --current) CURRENT=1 ;;
  esac
done

notify_pushover() {
  local message="$1"
  if [[ -z "$PUSHOVER_TOKEN" || -z "$PUSHOVER_USER" ]]; then
    return 0
  fi
  curl -fsS \
    --form-string "token=$PUSHOVER_TOKEN" \
    --form-string "user=$PUSHOVER_USER" \
    --form-string "title=$PUSHOVER_TITLE" \
    --form-string "message=$message" \
    https://api.pushover.net/1/messages.json >/dev/null || true
}

release_deploy_lock() {
  if [[ "${DEPLOY_LOCK_MODE:-}" == "dir" ]]; then
    rmdir "$DEPLOY_LOCK_PATH" >/dev/null 2>&1 || true
  fi
}

acquire_deploy_lock() {
  mkdir -p "$(dirname "$DEPLOY_LOCK_PATH")" || true

  if command -v flock >/dev/null 2>&1; then
    exec 9>"$DEPLOY_LOCK_PATH"
    if ! flock -n 9; then
      abort_deploy "Deploy aborted: another deploy appears to be running ($DEPLOY_LOCK_PATH)"
    fi
    DEPLOY_LOCK_MODE="flock"
    return 0
  fi

  if ! mkdir "$DEPLOY_LOCK_PATH" 2>/dev/null; then
    abort_deploy "Deploy aborted: another deploy appears to be running ($DEPLOY_LOCK_PATH)"
  fi

  DEPLOY_LOCK_MODE="dir"
  trap release_deploy_lock EXIT
}

abort_deploy() {
  local message="$1"
  echo "$message"
  notify_pushover "$message"
  exit 1
}

on_error() {
  local exit_code=$?

  # Best-effort: if we started a deploy, try to bring the app back up
  if [[ "${DID_DEPLOY:-0}" -eq 1 ]]; then
    ( run_app "cd $WORKDIR && php artisan up" ) >/dev/null 2>&1 || true
    notify_pushover "Deploy FAILED (exit $exit_code). Target: ${DEPLOY_LABEL:-unknown}"
  fi

  exit $exit_code
}
trap on_error ERR

run_app() {
  bash -lc "$*"
}

set_env_value() {
  local key="$1"
  local value="$2"
  local env_file="$WORKDIR/.env"
  local tmp_file

  tmp_file="$(mktemp "$WORKDIR/.env.tmp.XXXXXX")"
  if [[ -f "$env_file" ]]; then
    awk -v key="$key" -v value="$value" '
      BEGIN { prefix = key "="; found = 0 }
      index($0, prefix) == 1 { print prefix value; found = 1; next }
      { print }
      END { if (found == 0) print prefix value }
    ' "$env_file" > "$tmp_file"
  else
    printf '%s=%s\n' "$key" "$value" > "$tmp_file"
  fi

  mv "$tmp_file" "$env_file"
  chmod 664 "$env_file" || true
}

check_runtime_tools() {
  command -v php >/dev/null 2>&1 || abort_deploy "Deploy aborted: php not found"
  command -v composer >/dev/null 2>&1 || abort_deploy "Deploy aborted: composer not found"
  command -v node >/dev/null 2>&1 || abort_deploy "Deploy aborted: node not found"
  command -v npm >/dev/null 2>&1 || abort_deploy "Deploy aborted: npm not found"
  command -v git >/dev/null 2>&1 || abort_deploy "Deploy aborted: git not found"
}

ensure_repo_present() {
  mkdir -p "$WORKDIR"
  mkdir -p "$(dirname "$RELEASE_FILE")" || true

  if [[ ! -d "$WORKDIR/.git" ]]; then
    echo "Empty directory detected. Performing initial clone..."
    run_app "git clone \"$REPO_URL\" \"$WORKDIR\""
  fi

  # For newer git versions
  run_app "git config --global --add safe.directory $WORKDIR"
}

get_latest_tag() {
  # Fetch tags, then pick the newest tag by tag commit date.
  run_app "cd $WORKDIR && git fetch --tags --prune origin"
  run_app "cd $WORKDIR && git describe --tags \$(git rev-list --tags --max-count=1)"
}

get_latest_main_commit() {
  run_app "cd $WORKDIR && git fetch --prune origin main"
  run_app "cd $WORKDIR && git rev-parse origin/main"
}

get_commit_for_tag() {
  local tag="$1"
  run_app "cd $WORKDIR && git rev-list -n 1 \"$tag\""
}

is_ancestor() {
  local older="$1"
  local newer="$2"
  run_app "cd $WORKDIR && git merge-base --is-ancestor \"$older\" \"$newer\""
}

parse_last_release() {
  # Sets: LAST_TYPE, LAST_REF, LAST_COMMIT
  LAST_TYPE=""
  LAST_REF=""
  LAST_COMMIT=""

  [[ -f "$RELEASE_FILE" ]] || return 0

  # New key=value format
  if grep -q '^TYPE=' "$RELEASE_FILE" 2>/dev/null; then
    LAST_TYPE="$(grep -E '^TYPE=' "$RELEASE_FILE" | head -n1 | cut -d= -f2- || true)"
    LAST_REF="$(grep -E '^REF=' "$RELEASE_FILE" | head -n1 | cut -d= -f2- || true)"
    LAST_COMMIT="$(grep -E '^COMMIT=' "$RELEASE_FILE" | head -n1 | cut -d= -f2- || true)"
    return 0
  fi

  # Back-compat old single-line formats:
  # - tag:v1.2.3
  # - main:abcdef...
  local line
  line="$(cat "$RELEASE_FILE" 2>/dev/null || true)"
  case "$line" in
    tag:*)
      LAST_TYPE="tag"
      LAST_REF="${line#tag:}"
      ;;
    main:*)
      LAST_TYPE="commit"
      LAST_REF="main"
      LAST_COMMIT="${line#main:}"
      ;;
    *)
      # Unknown format, ignore
      ;;
  esac
}

write_last_release() {
  local type="$1"   # tag | commit
  local ref="$2"    # tag name or "main"
  local commit="$3" # full hash

  mkdir -p "$(dirname "$RELEASE_FILE")" || true
  {
    echo "TYPE=$type"
    echo "REF=$ref"
    echo "COMMIT=$commit"
  } > "$RELEASE_FILE"
  chmod 664 "$RELEASE_FILE" || true
}

fix_permissions() {
  chmod 755 "$WORKDIR" || true
  chmod 755 "$WORKDIR/public" || true

  find "$WORKDIR/public" -type d -exec chmod 755 {} \; || true
  find "$WORKDIR/public" -type f -exec chmod 644 {} \; || true
  [[ -f "$WORKDIR/public/.htaccess" ]] && chmod 644 "$WORKDIR/public/.htaccess" || true

  chmod -R 775 "$WORKDIR/storage" "$WORKDIR/bootstrap/cache" || true

  if [[ "$(id -u)" -eq 0 ]]; then
    chown -R www-data:www-data "$WORKDIR/storage" "$WORKDIR/bootstrap/cache" || true
  fi
}

if [[ "$CURRENT" -eq 1 ]]; then
  echo "Starting current-main deploy. Repo: $REPO_URL"
else
  echo "Starting release-based deploy. Repo: $REPO_URL"
fi

ensure_repo_present
acquire_deploy_lock
parse_last_release

# Decide target and whether to deploy
TARGET_REF=""
DEPLOY_LABEL=""

if [[ "$CURRENT" -eq 1 ]]; then
  TARGET_REF="$(get_latest_main_commit)"
  DEPLOY_LABEL="main@${TARGET_REF:0:10}"

  if [[ "$FORCE" -ne 1 && -n "${LAST_COMMIT:-}" && "$TARGET_REF" == "$LAST_COMMIT" ]]; then
    notify_pushover "Deploy skipped: no new deployment target detected ($DEPLOY_LABEL)"
    echo "No new deployment target detected ($DEPLOY_LABEL). Skipping deploy. Use --force to redeploy."
    exit 0
  fi
else
  LATEST_TAG="$(get_latest_tag)"
  if [[ -z "$LATEST_TAG" ]]; then
    echo "No tags found. Create a release tag (e.g. v1.0.0) before using this deploy script."
    exit 1
  fi

  LATEST_TAG_COMMIT="$(get_commit_for_tag "$LATEST_TAG")"
  TARGET_REF="$LATEST_TAG"
  DEPLOY_LABEL="$LATEST_TAG"

  if [[ "$FORCE" -ne 1 ]]; then
    # Baseline commit is what we last deployed (tag commit or commit).
    BASE_COMMIT=""

    if [[ "${LAST_TYPE:-}" == "tag" && -n "${LAST_REF:-}" ]]; then
      # Make sure tags are present locally
      run_app "cd $WORKDIR && git fetch --tags --prune origin" >/dev/null 2>&1 || true
      if run_app "cd $WORKDIR && git rev-parse -q --verify \"$LAST_REF\" >/dev/null 2>&1"; then
        BASE_COMMIT="$(get_commit_for_tag "$LAST_REF")"
      fi
    elif [[ "${LAST_TYPE:-}" == "commit" && -n "${LAST_COMMIT:-}" ]]; then
      BASE_COMMIT="$LAST_COMMIT"
    elif [[ -n "${LAST_COMMIT:-}" ]]; then
      # If TYPE missing but COMMIT exists (future proof)
      BASE_COMMIT="$LAST_COMMIT"
    fi

    if [[ -n "$BASE_COMMIT" ]]; then
      if [[ "$BASE_COMMIT" == "$LATEST_TAG_COMMIT" ]]; then
        notify_pushover "Deploy skipped: already at latest release target ($LATEST_TAG)"
        echo "Already at latest release tag target ($LATEST_TAG). Skipping deploy."
        exit 0
      fi

      # Deploy only if latest tag commit is newer in history than baseline commit
      if ! is_ancestor "$BASE_COMMIT" "$LATEST_TAG_COMMIT"; then
        notify_pushover "Deploy skipped: latest tag $LATEST_TAG ($LATEST_TAG_COMMIT) is not newer than deployed baseline ($BASE_COMMIT)"
        echo "Latest tag $LATEST_TAG ($LATEST_TAG_COMMIT) is not newer than deployed baseline ($BASE_COMMIT). Skipping deploy."
        exit 0
      fi
    fi
  fi
fi

echo "Deploying target: $DEPLOY_LABEL"
DID_DEPLOY=1
notify_pushover "Deploy starting: $DEPLOY_LABEL"
check_runtime_tools

# Put app into maintenance mode before mutating the checked-out code or built assets.
run_app "cd $WORKDIR && php artisan down || true"

# Checkout the selected target
if [[ "$CURRENT" -eq 1 ]]; then
  run_app "cd $WORKDIR && git fetch --prune origin main"
  run_app "cd $WORKDIR && git checkout -f origin/main"
else
  run_app "cd $WORKDIR && git fetch --tags origin"
  run_app "cd $WORKDIR && git checkout -f \"$TARGET_REF\""
fi

COMMIT_HASH="$(run_app "cd $WORKDIR && git rev-parse HEAD")"
SHORT_HASH="${COMMIT_HASH:0:10}"

# Lockfile safety
[[ -f "$WORKDIR/composer.lock" ]] || abort_deploy "Deploy aborted: composer.lock missing"
[[ -f "$WORKDIR/package-lock.json" ]] || abort_deploy "Deploy aborted: package-lock.json missing"

# Permissions pre-install
fix_permissions

# Update version and commit in .env
if [[ "$CURRENT" -eq 1 ]]; then
  VERSION_STRING="main"
else
  VERSION_STRING="$TARGET_REF"
fi

set_env_value "APP_VERSION" "$VERSION_STRING"
set_env_value "APP_COMMIT" "$COMMIT_HASH"

# PHP deps
#run_app "cd $WORKDIR && composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader"
run_app "cd $WORKDIR && composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader --no-progress --classmap-authoritative"

# Frontend build (required for Vite manifest.json) (was npm install + npm run build)
#run_app "cd $WORKDIR && npm install"
run_app "cd $WORKDIR && rm -rf public/build node_modules"
run_app "cd $WORKDIR && npm ci"
run_app "cd $WORKDIR && npm run build"

# DB migrations
run_app "cd $WORKDIR && php artisan migrate --force"

# Permissions post-build
fix_permissions

# Clear/rebuild caches
#run_app "cd $WORKDIR && php artisan cache:clear"
#run_app "cd $WORKDIR && php artisan view:clear"
#run_app "cd $WORKDIR && php artisan config:clear"
#run_app "cd $WORKDIR && php artisan route:clear"
run_app "cd $WORKDIR && php artisan optimize:clear"
run_app "cd $WORKDIR && php artisan optimize"
run_app "cd $WORKDIR && php artisan queue:restart"

# Bring app back up
run_app "cd $WORKDIR && php artisan up"

# Record deployed state
if [[ "$CURRENT" -eq 1 ]]; then
  write_last_release "commit" "main" "$COMMIT_HASH"
else
  write_last_release "tag" "$TARGET_REF" "$COMMIT_HASH"
fi

echo "Deployment finished at $DEPLOY_LABEL ($COMMIT_HASH)."
notify_pushover "Deploy applied: $DEPLOY_LABEL ($SHORT_HASH)"
