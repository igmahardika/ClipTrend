#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "== PHP syntax lint =="
find app config database routes tests -name '*.php' -print0 | xargs -0 -n1 php -l >/tmp/cliptrend-v7-php-lint.log
cat /tmp/cliptrend-v7-php-lint.log | tail -n 5

echo "== Binary check =="
command -v "${FFMPEG_BIN:-ffmpeg}"
command -v "${FFPROBE_BIN:-ffprobe}"

echo "== FFmpeg real render smoke test =="
mkdir -p storage/app/tmp/v7-smoke
IN=storage/app/tmp/v7-smoke/source.mp4
OUT=storage/app/tmp/v7-smoke/output.mp4
ASS=storage/app/tmp/v7-smoke/subtitles.ass
"${FFMPEG_BIN:-ffmpeg}" -hide_banner -y -f lavfi -i testsrc=size=1280x720:rate=30 -f lavfi -i sine=frequency=440:duration=7 -t 7 -c:v libx264 -pix_fmt yuv420p -c:a aac "$IN" >/dev/null 2>&1
cat > "$ASS" <<'ASS'
[Script Info]
ScriptType: v4.00+
PlayResX: 1080
PlayResY: 1920
[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Default,DejaVu Sans,58,&H00FFFFFF,&H0000FFFF,&H00000000,&H64000000,-1,0,0,0,100,100,0,0,1,4,0,2,70,70,250,1
[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
Dialogue: 0,0:00:00.50,0:00:05.50,Default,,0,0,0,,CLIPTREND V7 REAL RENDER TEST
ASS
"${FFMPEG_BIN:-ffmpeg}" -hide_banner -y -i "$IN" -vf "split=2[main][bg];[bg]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,gblur=sigma=26[blur];[main]scale=1080:1920:force_original_aspect_ratio=decrease[fg];[blur][fg]overlay=(W-w)/2:(H-h)/2,ass='$(pwd)/$ASS',drawbox=x=0:y=h-16:w=iw*t/7:h=16:color=white@0.86:t=fill,format=yuv420p" -c:v libx264 -preset veryfast -crf 23 -c:a aac -shortest "$OUT" >/dev/null 2>&1
DIM=$("${FFPROBE_BIN:-ffprobe}" -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0 "$OUT")
if [[ "$DIM" != "1080,1920" ]]; then
  echo "Unexpected output dimension: $DIM" >&2
  exit 1
fi
echo "Render output OK: $OUT ($DIM)"

echo "== Config reminders =="
if [[ -z "${OPENAI_API_KEY:-}" && -z "${WHISPER_BIN:-}" ]]; then
  echo "WARNING: Real transcription requires OPENAI_API_KEY or WHISPER_BIN. V7 will fail analysis clearly if neither is configured."
fi
