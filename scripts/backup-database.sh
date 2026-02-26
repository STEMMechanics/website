#!/usr/bin/env bash
set -Eeuo pipefail

# Full MySQL backup with gzip compression and retention pruning.
# - Includes schema + data + triggers/events/routines
# - Includes CREATE DATABASE and USE statements
# - Preserves AUTO_INCREMENT values via schema dump
#
# Usage:
#   ./scripts/backup-database.sh
#   DB_BACKUP_DOCKER_CONTAINER=laravel-db ./scripts/backup-database.sh
#   DB_BACKUP_DIR=/var/backups/stemmechanics DB_BACKUP_KEEP=336 ./scripts/backup-database.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
ENV_FILE="${ENV_FILE:-${PROJECT_ROOT}/.env}"

if [[ ! -f "${ENV_FILE}" ]]; then
  echo "ERROR: .env file not found at ${ENV_FILE}" >&2
  exit 1
fi

read_env() {
  local key="$1"
  local line value
  line="$(grep -E "^${key}=" "${ENV_FILE}" | tail -n 1 || true)"
  if [[ -z "${line}" ]]; then
    return 1
  fi

  value="${line#*=}"
  value="${value%\"}"
  value="${value#\"}"
  value="${value%\'}"
  value="${value#\'}"
  printf '%s' "${value}"
}

DB_CONNECTION="$(read_env DB_CONNECTION || true)"
DB_HOST="$(read_env DB_HOST || true)"
DB_PORT="$(read_env DB_PORT || true)"
DB_DATABASE="$(read_env DB_DATABASE || true)"
DB_USERNAME="$(read_env DB_USERNAME || true)"
DB_PASSWORD="$(read_env DB_PASSWORD || true)"

if [[ "${DB_CONNECTION}" != "mysql" ]]; then
  echo "ERROR: This script currently supports DB_CONNECTION=mysql only (found: ${DB_CONNECTION:-unset})." >&2
  exit 1
fi

if [[ -z "${DB_DATABASE}" || -z "${DB_USERNAME}" ]]; then
  echo "ERROR: DB_DATABASE and DB_USERNAME must be set in ${ENV_FILE}." >&2
  exit 1
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

BACKUP_DIR="${DB_BACKUP_DIR:-${PROJECT_ROOT}/storage/app/backups/database}"
KEEP_COUNT="${DB_BACKUP_KEEP:-168}" # 7 days at hourly frequency
DOCKER_CONTAINER="${DB_BACKUP_DOCKER_CONTAINER:-}"

mkdir -p "${BACKUP_DIR}"

timestamp="$(date +"%Y%m%d_%H%M%S")"
base_name="${DB_DATABASE}_${timestamp}.sql"
tmp_gz="${BACKUP_DIR}/.${base_name}.gz.tmp"
final_gz="${BACKUP_DIR}/${base_name}.gz"

DUMP_ARGS=(
  "--host=${DB_HOST}"
  "--port=${DB_PORT}"
  "--user=${DB_USERNAME}"
  "--single-transaction"
  "--quick"
  "--routines"
  "--triggers"
  "--events"
  "--hex-blob"
  "--set-gtid-purged=OFF"
  "--default-character-set=utf8mb4"
  "--add-drop-database"
  "--databases" "${DB_DATABASE}"
)

if [[ -n "${DOCKER_CONTAINER}" ]]; then
  docker exec -e MYSQL_PWD="${DB_PASSWORD}" "${DOCKER_CONTAINER}" \
    mysqldump "${DUMP_ARGS[@]}" | gzip -9 > "${tmp_gz}"
else
  MYSQL_PWD="${DB_PASSWORD}" mysqldump "${DUMP_ARGS[@]}" | gzip -9 > "${tmp_gz}"
fi

if [[ ! -s "${tmp_gz}" ]]; then
  rm -f "${tmp_gz}"
  echo "ERROR: Backup output is empty." >&2
  exit 1
fi

mv "${tmp_gz}" "${final_gz}"

if [[ "${KEEP_COUNT}" =~ ^[0-9]+$ ]] && [[ "${KEEP_COUNT}" -gt 0 ]]; then
  mapfile -t backup_files < <(ls -1t "${BACKUP_DIR}"/*.sql.gz 2>/dev/null || true)
  if [[ "${#backup_files[@]}" -gt "${KEEP_COUNT}" ]]; then
    for old_file in "${backup_files[@]:${KEEP_COUNT}}"; do
      rm -f "${old_file}"
    done
  fi
fi

echo "Backup created: ${final_gz}"
