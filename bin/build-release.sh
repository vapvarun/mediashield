#!/usr/bin/env bash
#
# Build the customer zip for MediaShield (Free): dist/mediashield-{version}.zip.
#
# Usage: bash bin/build-release.sh [--skip-ci]
#
# 1. The version in the header, MEDIASHIELD_VERSION and readme Stable tag agree.
# 2. bin/local-ci.sh --no-journeys passes (skip with --skip-ci when CI just ran).
# 3. The admin and block bundles are built (npm run build).
# 4. A clean copy is staged through .distignore, and Composer installs the
#    runtime packages only (--no-dev) inside that copy; the dev vendor/ here
#    is never touched.
# 5. The zip is written with Python zipfile, deflating every entry.
# 6. REQUIRED_FILES are asserted in the zip itself, and no dev file leaks.
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

SLUG="mediashield"
MAIN="mediashield.php"
CONST="MEDIASHIELD_VERSION"

# Files the plugin cannot start or update without. Checked in the zip.
REQUIRED_FILES=(
	"mediashield.php"
	"uninstall.php"
	"readme.txt"
	"vendor/autoload.php"
	"vendor/woocommerce/action-scheduler/action-scheduler.php"
	"libs/edd-sl-sdk/edd-sl-sdk.php"
	"libs/edd-sl-sdk/src/Versions.php"
	"libs/package-signatures/package-signatures.php"
	"build/admin/index.js"
	"build/admin/index.asset.php"
	"build/blocks/video/block.json"
	"build/blocks/playlist/block.json"
	"build/blocks/my-videos/block.json"
	"build/blocks/my-videos/render.php"
	"includes/Core/Plugin.php"
)

red()   { printf '\033[31m%s\033[0m\n' "$*"; }
green() { printf '\033[32m%s\033[0m\n' "$*"; }
blue()  { printf '\033[34m%s\033[0m\n' "$*"; }
fail()  { red "FAIL: $*"; exit 1; }
step()  { blue "[$1] $2"; }

SKIP_CI=0
[ "${1:-}" = "--skip-ci" ] && SKIP_CI=1

step 1 "Version"
HDR=$(grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' "$MAIN" | sed -E 's/.*Version:[[:space:]]*//; s/[[:space:]]*$//')
CON=$(grep -m1 "define( '$CONST'" "$MAIN" | sed -E "s/.*'$CONST',[[:space:]]*'([^']+)'.*/\1/")
TAG=$(grep -m1 '^Stable tag:' readme.txt | sed -E 's/Stable tag:[[:space:]]*//; s/[[:space:]]*$//')
[ "$HDR" = "$CON" ] && [ "$HDR" = "$TAG" ] || fail "version mismatch: header=$HDR constant=$CON readme=$TAG"
VERSION="$HDR"
green "    v$VERSION"

if [ "$SKIP_CI" -eq 0 ]; then
	step 2 "Local CI"
	bash bin/local-ci.sh --no-journeys
else
	step 2 "Local CI skipped (--skip-ci)"
fi

step 3 "Build bundles"
npm run build >/dev/null

step 4 "Stage"
DIST="$ROOT/dist"
STAGE="$DIST/_stage/$SLUG"
ZIP="$DIST/$SLUG-$VERSION.zip"
rm -rf "$DIST/_stage" "$ZIP"
mkdir -p "$STAGE"
# .distignore drops vendor/bin and composer.json; the runtime vendor/ is
# rebuilt in the stage below, so the dev packages never reach it.
rsync -a \
	--exclude-from=".distignore" \
	--exclude="/vendor" \
	--exclude=".DS_Store" \
	--exclude=".gitkeep" \
	--exclude="**/.editorconfig" \
	--exclude="**/.eslintrc*" \
	--exclude="**/.prettierrc*" \
	--exclude="**/.gitignore" \
	--exclude="**/.gitattributes" \
	--exclude="**/phpunit.xml*" \
	--exclude="**/phpcs.xml*" \
	--exclude="**/webpack.config.js" \
	--exclude="**/package.json" \
	--exclude="**/package-lock.json" \
	--exclude="**/composer.lock" \
	./ "$STAGE/"
cp composer.json composer.lock "$STAGE/"
( cd "$STAGE" && composer install --no-dev --optimize-autoloader --ignore-platform-req=php --no-interaction --quiet )
rm -rf "$STAGE/composer.json" "$STAGE/composer.lock" "$STAGE/vendor/bin" "$STAGE/vendor/composer/platform_check.php"
find "$STAGE/vendor" -path '*/assets/src/*' -prune -exec rm -rf {} + 2>/dev/null || true
# Package docs and dev metadata inside vendor/ (README, CHANGELOG, tests).
find "$STAGE/vendor" \( -iname '*.md' -o -iname '*.rst' -o -name 'phpunit.xml*' -o -name '.gitattributes' -o -name '.gitignore' \) -type f -delete
find "$STAGE/vendor" -type d \( -iname 'tests' -o -iname 'test' -o -iname 'docs' \) -prune -exec rm -rf {} + 2>/dev/null || true

step 5 "Zip (deflate every entry)"
python3 - "$DIST/_stage" "$SLUG" "$ZIP" <<'PY'
import os, sys, zipfile
base, slug, out = sys.argv[1], sys.argv[2], sys.argv[3]
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for root, dirs, files in os.walk(os.path.join(base, slug)):
        dirs.sort()
        for name in sorted(files):
            full = os.path.join(root, name)
            z.write(full, os.path.relpath(full, base))
PY

step 6 "Verify the zip"
LIST=$(python3 -c "import sys,zipfile;print('\n'.join(zipfile.ZipFile(sys.argv[1]).namelist()))" "$ZIP")
for f in "${REQUIRED_FILES[@]}"; do
	grep -qx "$SLUG/$f" <<<"$LIST" || fail "missing in zip: $f"
done
# The admin screens are lazy chunks (src/admin, React.lazy): the zip needs them.
grep -qE "^$SLUG/build/admin/chunks/[a-z0-9-]+\.[0-9a-f]{8}\.js$" <<<"$LIST" || fail "missing in zip: build/admin/chunks/*.js"
LEAKS=$(grep -E "^$SLUG/(src|tests|audit|plan|docs|bin|node_modules|dist)/|/\.git/|CLAUDE\.md$|^$SLUG/composer\.(json|lock)$|phpstan|vendor/bin/|\.(js|css)\.map$|vendor/(phpunit|squizlabs|phpstan|yoast|wp-coding-standards)/" <<<"$LIST" || true)
[ -z "$LEAKS" ] || { red "$LEAKS"; fail "dev files in the zip"; }
STORED=$(python3 -c "import sys,zipfile;print(sum(1 for i in zipfile.ZipFile(sys.argv[1]).infolist() if i.compress_type==zipfile.ZIP_STORED and not i.is_dir()))" "$ZIP")
[ "$STORED" = "0" ] || fail "$STORED entries stored without deflate"
rm -rf "$DIST/_stage"

green "Release zip: $ZIP ($(du -h "$ZIP" | cut -f1), $(wc -l <<<"$LIST" | tr -d ' ') entries)"
