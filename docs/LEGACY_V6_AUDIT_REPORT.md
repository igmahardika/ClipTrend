# ClipTrend AI V7 Audit Report

## Tujuan V7
V7 dibuat sebagai rombakan stabil dari build sebelumnya. Fokusnya bukan lagi demo UI, tetapi flow nyata yang bisa diuji developer:

1. Upload video nyata.
2. Extract metadata nyata dengan FFprobe.
3. Extract audio nyata dengan FFmpeg jika audio stream tersedia.
4. Transcribe audio nyata dengan OpenAI Speech-to-Text atau local Whisper.
5. Jika video tanpa audio, jalankan visual-only analysis berbasis metadata dan scene changes nyata.
6. Deteksi niche/topik/copy pack dari transcript nyata, bukan dummy.
7. Generate kandidat clip dari timestamp transcript nyata, bukan timestamp hardcoded.
8. Generate subtitle dari transcript segment nyata.
9. Render MP4 1080x1920 dengan FFmpeg, subtitle burn-in, hook overlay, progress bar, dan optional watermark.
10. Preview dan download hasil render.

## Perubahan besar dari V5

### 1. Dummy default dihapus
`AI_PROVIDER=real` tetap default. Tanpa `OPENAI_API_KEY` atau `WHISPER_BIN`, video yang punya audio akan gagal secara eksplisit. Sistem tidak membuat transcript palsu.

### 2. Visual-only mode untuk video tanpa audio
Jika video tidak memiliki audio stream, sistem tidak memaksa transkripsi. Sistem membuat kandidat clip dari durasi, scene changes, dan metadata nyata. Subtitle kosong, tetapi hook overlay dan render tetap berjalan.

### 3. Content intelligence dipisah
File baru:

```text
app/Services/AI/TranscriptIntelligenceService.php
```

Service ini melakukan:
- klasifikasi niche dari transcript,
- LLM analysis via OpenAI Responses API jika `OPENAI_TEXT_MODEL` tersedia,
- fallback deterministic dari transcript nyata jika LLM text model tidak dikonfigurasi,
- ranking kandidat clip dari transcript window,
- copy pack title/caption/hashtag dari transcript nyata.

### 4. Rendering diperbaiki
`FfmpegVideoRenderer` sekarang:
- menerima hampir semua input yang bisa dibaca FFmpeg,
- memakai `fit_blur` sebagai mode aman untuk landscape/portrait/square,
- memaksa output 1080x1920, 30fps, H.264, AAC, yuv420p,
- mendukung input tanpa audio via `-map 0:a:0?`,
- membakar subtitle ASS,
- menambah hook text di 3 detik awal,
- menambah progress bar,
- menambah watermark opsional dari env.

### 5. Metadata lebih lengkap
`VideoMetadataService` sekarang menyimpan:
- duration,
- width/height setelah rotation correction,
- frame rate,
- bitrate,
- has_audio,
- has_video,
- audio codec,
- video codec,
- rotation.

## Hal yang sudah divalidasi di sandbox

```text
PHP syntax lint: PASSED
FFmpeg detected: PASSED
FFprobe detected: PASSED
FFmpeg 9:16 render smoke test: PASSED
Output render size: 1080x1920
```

## Hal yang tidak bisa divalidasi di sandbox
Sandbox tidak memiliki Composer dependency Laravel, Node modules, database server, OpenAI API key, dan local Whisper. Karena itu belum bisa menjalankan:

```bash
composer install
php artisan migrate --seed
php artisan route:list
php artisan test
npm install
npm run build
```

Developer perlu menjalankan checklist ini di environment lokal/server.

## Expected behavior

### Jika video punya audio dan API/key transcriber tersedia
Expected:

```text
upload → metadata → audio wav → transcript → niche → clip → subtitle → render → download
```

### Jika video punya audio tapi transcriber belum tersedia
Expected: analysis gagal dengan error jelas. Tidak ada dummy data.

### Jika video tidak punya audio
Expected:

```text
upload → metadata → visual-only analysis → visual clip candidates → render tanpa subtitle transcript → download
```
