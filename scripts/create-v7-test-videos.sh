#!/usr/bin/env bash
set -euo pipefail
mkdir -p storage/app/tmp
ffmpeg=${FFMPEG_BIN:-ffmpeg}
# Landscape talking-head style with tone audio
$ffmpeg -hide_banner -y -f lavfi -i testsrc=size=1280x720:rate=30 -f lavfi -i sine=frequency=440:duration=12 -t 12 -c:v libx264 -pix_fmt yuv420p -c:a aac storage/app/tmp/v7-landscape-with-audio.mp4 >/dev/null 2>&1
# Portrait video with tone audio
$ffmpeg -hide_banner -y -f lavfi -i testsrc=size=720x1280:rate=30 -f lavfi -i sine=frequency=660:duration=10 -t 10 -c:v libx264 -pix_fmt yuv420p -c:a aac storage/app/tmp/v7-portrait-with-audio.mp4 >/dev/null 2>&1
# Square visual-only video
$ffmpeg -hide_banner -y -f lavfi -i testsrc=size=900x900:rate=30 -t 8 -c:v libx264 -pix_fmt yuv420p storage/app/tmp/v7-square-no-audio.mp4 >/dev/null 2>&1
echo "Generated test videos in storage/app/tmp/"
