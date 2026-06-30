#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="$ROOT/storage/app/tmp/cliptrend-demo-source.mp4"
mkdir -p "$(dirname "$OUT")"
ffmpeg -y -f lavfi -i testsrc2=size=1280x720:rate=30 -f lavfi -i sine=frequency=660:sample_rate=44100 -t 12 -c:v libx264 -c:a aac "$OUT" -hide_banner -loglevel error
echo "Created $OUT"
