#!/usr/bin/env bash
# IBB Rentals — distribution build.
#
# Produces a clean zip suitable for upload via WP admin Plugins → Add New →
# Upload, or for committing to the WordPress.org plugin SVN repo.
#
# Two modes:
#   ./build.sh              # default — packages HEAD via `git archive`
#                           # (uses .gitattributes export-ignore rules)
#   ./build.sh --working    # packages your working tree (uncommitted
#                           # changes included). Uses .distignore via rsync.
#
# Both modes:
#   - Read the plugin version from the main file's header.
#   - Run composer in --no-dev mode if composer.json is present and `composer`
#     is on PATH (so vendor/ ships only production deps).
#   - Output to dist/ibb-rentals-<version>.zip.
#
# Cross-platform: tested on macOS, Linux, and Git Bash on Windows.

set -euo pipefail

PLUGIN_SLUG="ibb-rentals"
ROOT="$(cd "$(dirname "$0")" && pwd)"
DIST_DIR="$ROOT/dist"
MODE="${1:-archive}"

VERSION=$(grep -E '^\s*\*\s*Version:' "$ROOT/${PLUGIN_SLUG}.php" | head -1 | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
[ -n "$VERSION" ] || { echo "Could not parse Version from ${PLUGIN_SLUG}.php" >&2; exit 1; }

OUTPUT="$DIST_DIR/${PLUGIN_SLUG}-${VERSION}.zip"

mkdir -p "$DIST_DIR"
rm -f "$OUTPUT"

# Install / refresh production-only composer deps (if a composer.json exists
# and composer is available). Vendored deps are required for some features
# but the plugin self-degrades if vendor/ is absent.
if [ -f "$ROOT/composer.json" ] && command -v composer >/dev/null 2>&1; then
	echo "→ composer install --no-dev --optimize-autoloader"
	(cd "$ROOT" && composer install --no-dev --optimize-autoloader --quiet)
	if grep -q '"mozart"' "$ROOT/composer.json" 2>/dev/null; then
		echo "→ composer mozart-compose"
		(cd "$ROOT" && composer mozart-compose --quiet || true)
	fi
fi

case "$MODE" in
	--working)
		echo "→ Packaging working tree (rsync + .distignore)"
		STAGING="$DIST_DIR/_staging"
		rm -rf "$STAGING"
		mkdir -p "$STAGING/$PLUGIN_SLUG"

		# Build the rsync exclude list from .distignore.
		EXCLUDE_FILE="$DIST_DIR/_exclude.tmp"
		grep -vE '^\s*(#|$)' "$ROOT/.distignore" > "$EXCLUDE_FILE"

		rsync -a --exclude-from="$EXCLUDE_FILE" --exclude='dist/' --exclude='.git/' \
			"$ROOT/" "$STAGING/$PLUGIN_SLUG/"

		(cd "$STAGING" && zip -rq "$OUTPUT" "$PLUGIN_SLUG")
		rm -rf "$STAGING" "$EXCLUDE_FILE"
		;;

	*)
		echo "→ Packaging HEAD (git archive + .gitattributes export-ignore)"
		# git archive writes the zip with file paths relative to the repo root
		# but we want them prefixed with the plugin slug so unzip creates
		# `ibb-rentals/...` not just loose files.
		(cd "$ROOT" && git archive --format=zip --prefix="${PLUGIN_SLUG}/" HEAD > "$OUTPUT")

		# git archive doesn't include vendor/ if it's gitignored. Fold it in
		# now if it exists on disk.
		if [ -d "$ROOT/vendor" ]; then
			echo "→ Folding vendor/ into the archive"
			STAGING="$DIST_DIR/_vendor_staging/$PLUGIN_SLUG"
			mkdir -p "$STAGING"
			rsync -a "$ROOT/vendor" "$STAGING/"
			(cd "$DIST_DIR/_vendor_staging" && zip -rq "$OUTPUT" "$PLUGIN_SLUG/vendor")
			rm -rf "$DIST_DIR/_vendor_staging"
		fi
		;;
esac

echo
echo "Built $OUTPUT"
ls -la "$OUTPUT" 2>/dev/null || ls -la "$OUTPUT"
echo
echo "Inspect contents:"
echo "  unzip -l \"$OUTPUT\""
