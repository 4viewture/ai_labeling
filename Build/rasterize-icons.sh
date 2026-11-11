#!/usr/bin/env sh
# Rasterizes the compact EU "AI" symbol and its German variant "KI" (solid black / white variants, official SVGs in
# Resources/Public/Icons/EU) to PNG for burning into processed images
# (Classes/Resource/Processing/AiLabelImageProcessor.php). Requires rsvg-convert + ImageMagick 7.
# The transparent margin of the official artwork is trimmed; the artwork itself is unchanged.
set -eu
cd "$(dirname "$0")/.."
for acronym in ai ki; do
  for colour in black white; do
    tmp="$(mktemp -t ai-symbol).png"
    rsvg-convert -w 1024 -h 1024 "Resources/Public/Icons/EU/$acronym-symbol-$colour.svg" -o "$tmp"
    magick "$tmp" -trim +repage -resize 512x512 "PNG32:Resources/Private/Images/$acronym-symbol-$colour.png"
    rm -f "$tmp"
  done
done
echo "done"
