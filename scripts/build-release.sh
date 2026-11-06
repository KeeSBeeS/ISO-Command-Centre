#!/usr/bin/env bash
#
# Builds a ready-to-run ISO Command Centre release zip.
#
# The hosting has no SSH, so nothing can be installed on the server: the zip
# must already contain the production Composer dependencies (vendor/).
#
# Usage: scripts/build-release.sh <version>      e.g. scripts/build-release.sh v3.0.0
# Output: dist/iso-command-centre-<version>.zip and dist/iso-command-centre-<version>.zip.sha256

set -euo pipefail

VERSION="${1:?Usage: $0 <version>}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
NAME="iso-command-centre-$VERSION"
STAGE="$DIST/stage/$NAME"
ZIP="$NAME.zip"

cd "$ROOT"
rm -rf "$DIST"
mkdir -p "$STAGE"

echo "==> Copying application files"
# Everything the app needs at runtime. Tests, release notes, local env files
# and dev tooling are left out on purpose.
cp -R app bootstrap config database installer public resources routes "$STAGE/"
cp artisan composer.json composer.lock "$STAGE/"

# One-off hotfix scripts for v2.8.x sites must never reach the web folder.
rm -f "$STAGE"/public/iso-*hotfix*.php "$STAGE"/public/isoadmin-clear-cache-*.php

# Runtime directories must exist but ship empty; the server fills them.
mkdir -p "$STAGE/storage"
(cd storage && find . -type d) | while read -r dir; do
    mkdir -p "$STAGE/storage/$dir"
done
(cd storage && find . -name .gitignore) | while read -r file; do
    cp "storage/$file" "$STAGE/storage/$file"
done
find "$STAGE/bootstrap/cache" -type f ! -name .gitignore -delete
rm -f "$STAGE/public/hot" "$STAGE/database/database.sqlite"

echo "==> Installing production Composer dependencies"
composer install --working-dir="$STAGE" --no-dev --prefer-dist \
    --optimize-autoloader --no-interaction --no-progress --no-scripts
# package:discover normally runs as a Composer script; run it here so the
# shipped bootstrap/cache matches the production dependencies.
(cd "$STAGE" && php artisan package:discover --ansi)

# Packages installed from source carry their git history; never ship it.
find "$STAGE/vendor" -name .git -prune -exec rm -rf {} +

echo "$VERSION" > "$STAGE/VERSION"

echo "==> Creating $ZIP"
(cd "$DIST/stage" && zip -qr "$DIST/$ZIP" "$NAME")
(cd "$DIST" && sha256sum "$ZIP" > "$ZIP.sha256")
rm -rf "$DIST/stage"

echo "==> Done: dist/$ZIP"
cat "$DIST/$ZIP.sha256"
