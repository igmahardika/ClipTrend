#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "== ClipTrend V7 smoke check =="
php -v >/dev/null
find app config routes database -name '*.php' -print0 | xargs -0 -n1 php -l >/tmp/cliptrend-v7-php-lint.log
cat /tmp/cliptrend-v7-php-lint.log | tail -n 5

command -v ffmpeg >/dev/null || { echo "FFmpeg is missing"; exit 1; }
command -v ffprobe >/dev/null || { echo "FFprobe is missing"; exit 1; }

TMP="${ROOT}/storage/app/tmp/v7-smoke"
rm -rf "$TMP"
mkdir -p "$TMP"
ffmpeg -y -f lavfi -i testsrc2=size=1280x720:rate=30 -f lavfi -i sine=frequency=1000:sample_rate=44100 -t 3 -c:v libx264 -c:a aac "$TMP/input-landscape.mp4" -hide_banner -loglevel error
cat > "$TMP/subs.ass" <<'ASS'
[Script Info]
ScriptType: v4.00+
PlayResX: 1080
PlayResY: 1920
ScaledBorderAndShadow: yes
[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Caption,DejaVu Sans,58,&H00FFFFFF,&H000000FF,&H00111111,&HAA000000,-1,0,0,0,100,100,0,0,3,3,0,2,70,70,230,1
Style: Hook,DejaVu Sans,62,&H00FFFFFF,&H000000FF,&H00111111,&HAA000000,-1,0,0,0,100,100,0,0,3,3,0,8,70,70,165,1
[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
Dialogue: 1,0:00:00.00,0:00:02.00,Hook,,0,0,0,,INI HOOK VIDEO
Dialogue: 0,0:00:00.20,0:00:02.80,Caption,,0,0,0,,CONTOH SUBTITLE NYATA
ASS
VF="split=2[main][bg];[bg]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,gblur=sigma=26,eq=brightness=-0.05:saturation=0.9[blur];[main]scale=1080:1920:force_original_aspect_ratio=decrease[fg];[blur][fg]overlay=(W-w)/2:(H-h)/2,setsar=1,fps=30,ass='$TMP/subs.ass',drawbox=x=0:y=h-16:w=iw*t/3:h=16:color=white@0.86:t=fill,format=yuv420p"
ffmpeg -y -hide_banner -i "$TMP/input-landscape.mp4" -t 3 -vf "$VF" -map 0:v:0 -map 0:a:0? -c:v libx264 -preset veryfast -crf 23 -pix_fmt yuv420p -c:a aac -b:a 160k -movflags +faststart -shortest "$TMP/output-vertical.mp4" -loglevel error
SIZE=$(ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0 "$TMP/output-vertical.mp4")
if [ "$SIZE" != "1080,1920" ]; then
  echo "Unexpected render size: $SIZE"
  exit 1
fi

echo "FFmpeg render test: PASSED ($SIZE)"
echo "V7 smoke check: PASSED"
