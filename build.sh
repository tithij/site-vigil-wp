#!/usr/bin/env bash
# Build the WordPress-installable ZIP for the Site Vigil plugin.
# Run from the site-vigil-wp directory:
#   ./build.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST="$SCRIPT_DIR/dist"

VERSION="$(grep -m1 -oE '^ \* Version:\s*[0-9][^\s]*' "$SCRIPT_DIR/site-vigil.php" | sed -E 's/^ \* Version:\s*//')"
if [ -z "$VERSION" ]; then
    echo "Could not read Version: header from site-vigil.php" >&2
    exit 1
fi

DEST="$DIST/site-vigil-wp-$VERSION.zip"
mkdir -p "$DIST"
rm -f "$DEST"

if command -v wp >/dev/null 2>&1; then
    # wp-dist-archive respects .distignore and produces a correctly-named,
    # correctly-rooted zip on its own.
    wp dist-archive "$SCRIPT_DIR" "$DEST"
else
    # Fallback: stage a clean copy honoring .distignore, then zip its
    # contents (not the staging folder itself — WP just needs a single
    # top-level plugin folder inside the zip, which STAGE already is named).
    STAGE="$(mktemp -d)/site-vigil"
    mkdir -p "$STAGE"

    RSYNC_EXCLUDES=()
    while IFS= read -r pattern; do
        [ -z "$pattern" ] && continue
        RSYNC_EXCLUDES+=(--exclude "$pattern")
    done < "$SCRIPT_DIR/.distignore"

    rsync -a "${RSYNC_EXCLUDES[@]}" --exclude dist "$SCRIPT_DIR/" "$STAGE/"

    (cd "$(dirname "$STAGE")" && zip -r "$DEST" "$(basename "$STAGE")" -x "*.DS_Store")
    rm -rf "$(dirname "$STAGE")"
fi

echo "Package created: $DEST"
