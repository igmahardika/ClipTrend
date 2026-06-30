# V7 Rebuild Report

## Objective

Rombak ClipTrend dari prototype/scaffold menjadi commercial-beta Laravel app dengan real processing pipeline. Prioritas V7 adalah menghapus hardcoded final output dan memastikan data yang dipakai berasal dari video nyata: metadata FFprobe, audio extraction, transcript, timestamp transcript, scene signal, dan FFmpeg render output.

## Core Architecture

```text
Upload
→ Validate
→ Store original private file
→ FFprobe metadata
→ Normalize to working MP4
→ Extract thumbnail
→ Extract audio WAV
→ Real transcription OpenAI/local Whisper
→ Niche/topic/audience/content-style analysis
→ Clip candidate detection
→ Subtitle generation
→ Review/edit
→ FFmpeg render 9:16
→ Preview/download/export copy pack
```

## V7 Hard Rules

1. No fake transcript.
2. No fake final caption from dummy data.
3. No render from missing source file.
4. No claim that TikTok/Google Trends works without real provider.
5. Video with audio requires real transcription provider.
6. Video without audio uses visual-only mode and has no generated speech subtitles.
7. All uploaded video is normalized before processing by default.
8. Render uses normalized working media when available.

## Major Fixes From V7

- Added `VideoNormalizationService`.
- Added `WorkingMediaResolver`.
- Audio extraction now uses normalized working file.
- Rendering now uses normalized working file.
- Video signal detection now uses normalized working file.
- Upload rejects unreadable/non-video files early.
- Project purge deletes original, normalized, thumbnail, audio, output files.
- Docker Compose added for production-like local test.
- API status endpoints added for frontend polling.
- V7 smoke test validates PHP lint and FFmpeg 1080x1920 render.

## Commercial Readiness Status

V7 is ready for serious developer QA and client-demo hardening. It is not a guarantee of zero bugs because the sandbox cannot run Composer/NPM/artisan fully. Final sales readiness must be approved after real environment tests listed in QA checklist.
