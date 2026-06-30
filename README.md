# ClipTrend AI V7 — Real Shorts Studio

ClipTrend AI V7 adalah rebuild Laravel untuk aplikasi SaaS AI video repurposing: upload video panjang, normalisasi media, transkripsi nyata, analisis niche, rekomendasi clip, subtitle editable, render 9:16, preview, download, dan copy pack untuk YouTube Shorts, TikTok, dan Instagram Reels.

V7 tidak membuat hasil final palsu. Jika transkripsi nyata belum dikonfigurasi, analysis akan gagal dengan pesan jelas. Untuk video tanpa audio, pipeline masuk visual-only mode dan tidak membuat subtitle palsu.

## Stack

- Laravel 13 / PHP 8.3+
- Blade + Tailwind CSS + Vite
- MySQL/PostgreSQL/SQLite untuk local test
- Redis Queue untuk production
- FFmpeg + FFprobe untuk ingest, normalization, thumbnail, subtitle burn-in, render
- OpenAI Speech-to-Text atau local Whisper untuk transkripsi nyata
- OpenAI Responses API optional untuk semantic analysis/copy pack
- Laravel filesystem abstraction untuk local/S3-compatible storage

Laravel 13 membutuhkan PHP 8.3+. Lihat release notes Laravel resmi.

## Fitur V7 yang Aktif

1. Auth user/admin.
2. Project video per user.
3. Upload video nyata.
4. Validasi video.
5. FFprobe metadata extraction.
6. FFmpeg normalization ke working MP4 H.264/AAC.
7. Thumbnail extraction.
8. Audio extraction WAV 16 kHz.
9. Real transcription via OpenAI atau local Whisper.
10. Niche detection dari transcript/metadata nyata.
11. Topic/audience/content-style detection.
12. Clip candidate dari timestamp transcript atau scene signal visual-only.
13. Subtitle generation dari transcript segments.
14. Subtitle editor.
15. Render 1080x1920 dengan FFmpeg.
16. Fit-blur mode untuk landscape/portrait/square.
17. ASS subtitle burn-in.
18. Hook text overlay.
19. Progress bar overlay.
20. Watermark optional.
21. Render queue dan retry.
22. Output library, preview, download, copy title/caption/hashtags.
23. Admin dashboard basic.
24. API status endpoint untuk polling.
25. Docker Compose untuk app + queue + MySQL + Redis.

## Batasan Jujur

V7 adalah commercial-beta codebase yang siap dites serius oleh developer, bukan klaim bahwa aplikasi otomatis setara penuh dengan OpusClip/Klap/Submagic secara maturity. Untuk benar-benar siap jual publik, tetap perlu QA real-device, load test, payment/quota, monitoring, dan legal review.

V7 memastikan fitur inti tidak dummy: upload, normalize, transcribe, analyze, generate clip/subtitle, render, preview, download.

## Instalasi Lokal Cepat

```bash
cd cliptrend-laravel-v7
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
bash scripts/v7-smoke-check.sh
php artisan serve
```

Queue local sederhana:

```env
QUEUE_CONNECTION=sync
CACHE_STORE=file
```

Production-like:

```bash
php artisan queue:work redis --queue=analysis,rendering,default --timeout=3600 --tries=3
```

## Docker Development

```bash
cp .env.example .env
docker compose up --build
```

Setelah container siap:

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm install
docker compose exec app npm run build
```

Akses: `http://localhost:8080`

## Konfigurasi AI Nyata

Pilih salah satu transcriber.

### OpenAI

```env
AI_PROVIDER=real
OPENAI_API_KEY=ISI_KEY_BARU_ANDA
OPENAI_TRANSCRIPTION_MODEL=whisper-1
OPENAI_TEXT_MODEL=gpt-4o-mini
```

### Local Whisper

```env
AI_PROVIDER=real
WHISPER_BIN=/usr/local/bin/whisper
WHISPER_MODEL=base
WHISPER_LANGUAGE=Indonesian
```

Tanpa `OPENAI_API_KEY` atau `WHISPER_BIN`, video yang punya audio akan gagal di tahap analysis dengan error jelas. Ini disengaja agar output tidak dummy.

## Konfigurasi FFmpeg

```env
FFMPEG_BIN=/usr/bin/ffmpeg
FFPROBE_BIN=/usr/bin/ffprobe
NORMALIZE_ON_UPLOAD=true
NORMALIZE_CRF=20
RENDER_CRF=23
DEFAULT_CROP_MODE=fit_blur
WATERMARK_TEXT=ClipTrend AI
```

## Test Video

```bash
bash scripts/create-v7-test-videos.sh
```

File test akan dibuat di `storage/app/tmp/`.

## Default Login

```text
Admin: admin@cliptrend.local
Password: password
```

## Flow Test

1. Login.
2. Create Project.
3. Upload video nyata.
4. Tunggu analysis.
5. Review niche, copy pack, clips, subtitle.
6. Edit subtitle/hook/caption jika perlu.
7. Render clip ke Shorts/TikTok/Reels.
8. Preview output.
9. Download MP4 dan copy title/caption/hashtag.

## Endpoint Status API

Gunakan Sanctum token.

```http
GET /api/projects/{project}/status
GET /api/render-jobs/{renderJob}/status
```

## Legal YouTube Source

YouTube ingestion default disabled. Aktifkan hanya untuk konten milik sendiri/authorized.

```env
YOUTUBE_INGESTION_ENABLED=true
YTDLP_BIN=/usr/local/bin/yt-dlp
```

## Production Checklist

- Set `APP_ENV=production`, `APP_DEBUG=false`.
- Gunakan MySQL/PostgreSQL production.
- Gunakan Redis queue.
- Jalankan supervisor/systemd untuk queue worker.
- Konfigurasi storage S3-compatible jika file besar.
- Pasang monitoring log dan alert.
- Batasi upload size dari Nginx/PHP/Laravel.
- Jangan commit `.env` atau API key.
- Revoke API key yang pernah bocor.
