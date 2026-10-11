#!/bin/sh
# Re-render the email signature band background into public/images/signature/iiso-signature-bg.jpg.
#
#   sh resources/signature/render.sh              regenerate bg.svg from bg_render.cjs, then render it
#   sh resources/signature/render.sh --from-svg   render bg.svg as it is (after editing it by hand)
#
# Needs Node with the playwright package (and its Chromium) plus ImageMagick "convert".
# Run it on a workstation, then upload the JPG; the shared host needs no build step.
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
OUT="$DIR/../../public/images/signature/iiso-signature-bg.jpg"
PNG="$(mktemp "${TMPDIR:-/tmp}/iiso-signature-bg.XXXXXX.png")"
trap 'rm -f "$PNG"' EXIT

NODE_PATH="${NODE_PATH:-/opt/node22/lib/node_modules}" node "$DIR/bg_render.cjs" "$@" "$PNG"
# 1280 x 264 (2x the 640 x 132 band), progressive JPEG q82 with 4:2:0 chroma: about 30 KB
convert "$PNG" -strip -interlace Plane -sampling-factor 4:2:0 -quality 82 "$OUT"
ls -l "$OUT"
