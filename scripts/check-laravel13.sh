#!/usr/bin/env bash
# Resolve an upgrade without touching the application's dependencies or executing scripts.
set -euo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
upgrade_dir="$(mktemp -d "${TMPDIR:-/tmp}/stemmechanics-laravel13.XXXXXX")"
trap 'rm -rf "$upgrade_dir"' EXIT
cp "$repo_root/composer.json" "$repo_root/composer.lock" "$upgrade_dir/"
php -r '$path = $argv[1]; $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); $data["require"]["laravel/framework"] = "^13.0"; file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);' "$upgrade_dir/composer.json"
composer --working-dir="$upgrade_dir" update laravel/framework --with-all-dependencies --dry-run --no-scripts --no-interaction
