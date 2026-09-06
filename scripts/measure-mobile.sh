#!/usr/bin/env bash
set -euo pipefail
if [[ $# -ne 3 ]]; then
    echo 'Usage: scripts/measure-mobile.sh HOME_URL STORE_URL WORKSHOP_URL' >&2
    exit 2
fi
output_dir="${LIGHTHOUSE_OUTPUT_DIR:-/tmp/stemmechanics-lighthouse}"
mkdir -p "$output_dir"
names=(home store workshop)
index=0
for url in "$@"; do
    npx --yes lighthouse@13.0.3 "$url" --chrome-flags='--headless' \
        --only-categories=performance,accessibility,best-practices,seo \
        --output=json --output=html --output-path="$output_dir/${names[$index]}" --quiet
    index=$((index + 1))
done
echo "Reports saved to $output_dir"
