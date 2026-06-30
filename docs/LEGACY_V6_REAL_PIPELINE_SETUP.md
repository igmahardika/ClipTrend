# V7 Real Pipeline Setup

## Minimum local setup

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Untuk test sederhana tanpa Redis:

```env
QUEUE_CONNECTION=sync
CACHE_STORE=file
SESSION_DRIVER=file
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

## FFmpeg

Ubuntu/Debian:

```bash
sudo apt update
sudo apt install -y ffmpeg
which ffmpeg
which ffprobe
```

Pastikan `.env`:

```env
FFMPEG_BIN=/usr/bin/ffmpeg
FFPROBE_BIN=/usr/bin/ffprobe
```

## Real transcription option A: OpenAI

```env
AI_PROVIDER=real
OPENAI_API_KEY=your-new-key
OPENAI_TRANSCRIPTION_MODEL=whisper-1
OPENAI_TEXT_MODEL=gpt-4o-mini
TRANSCRIPTION_LANGUAGE=id
```

`OPENAI_TRANSCRIPTION_MODEL` dipakai untuk speech-to-text. `OPENAI_TEXT_MODEL` dipakai untuk semantic niche detection, clip ranking, title/caption/hashtag. Jika `OPENAI_TEXT_MODEL` kosong, V7 tetap memakai deterministic analysis dari transcript nyata.

## Real transcription option B: local Whisper

```bash
python3 -m pip install -U openai-whisper
which whisper
```

```env
AI_PROVIDER=real
WHISPER_BIN=/usr/local/bin/whisper
WHISPER_MODEL=base
WHISPER_LANGUAGE=Indonesian
WHISPER_FP16=false
```

## Queue production

```env
QUEUE_CONNECTION=redis
```

Run worker:

```bash
php artisan queue:work redis --queue=analysis,rendering,default --timeout=7200 --tries=3
```

## Test video

Generate sample:

```bash
bash scripts/create-test-video.sh
```

Run smoke check:

```bash
bash scripts/v7-smoke-check.sh
```

## Upload test flow

1. Login admin: `admin@cliptrend.local` / `password`.
2. Create Project.
3. Upload video.
4. Tunggu analysis.
5. Cek AI Niche Detection.
6. Pilih clip.
7. Edit subtitle/caption jika perlu.
8. Render.
9. Preview output.
10. Download MP4 dan copy caption/hashtag.

## Important behavior

- V7 tidak membuat transcript dummy.
- Video dengan audio wajib punya OpenAI key atau local Whisper.
- Video tanpa audio tetap bisa dirender dan dipotong, tetapi subtitle transcript tidak akan ada.
- YouTube ingestion default off untuk alasan legal/safety. Aktifkan hanya untuk owned/authorized content.
