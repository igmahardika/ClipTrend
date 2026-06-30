# Technical Architecture — ClipTrend AI V7

## Stack

- Laravel 13 / PHP 8.3+
- Blade + Tailwind CSS
- SQLite local, MySQL/PostgreSQL production
- Laravel Queue: sync local, Redis production
- FFmpeg/FFprobe for metadata, audio extraction, render
- OpenAI Speech-to-Text or local Whisper for real transcription
- Optional OpenAI Responses API text model for semantic niche/copy/clip ranking
- Local/S3-compatible storage via Laravel filesystem

## V7 Real Processing Flow

```text
Upload source video
  ↓
FFprobe metadata extraction
  ↓
If audio exists: FFmpeg WAV extraction
  ↓
OpenAI/local Whisper transcription
  ↓
VideoSignalService: silence + scene-change signals
  ↓
TranscriptIntelligenceService
  ├─ OpenAI semantic analysis if OPENAI_TEXT_MODEL exists
  └─ Real transcript rule-engine fallback if text model is not set
  ↓
Niche detection + topic + audience + platform recommendation
  ↓
Transcript window scoring + optional OpenAI ranking
  ↓
Clip creation + real subtitle segments
  ↓
User review/edit
  ↓
RenderClipJob
  ↓
FFmpeg 1080x1920 H.264/AAC output with ASS subtitles, hook, progress bar, optional watermark
  ↓
Output Library download/copy pack
```

## Video without audio

If a video has no audio stream, V7 does not create fake transcript. It enters visual-only mode:

```text
FFprobe metadata + scene changes → visual clip candidates → render without transcript subtitles
```

## Main Services

- `VideoIngestionService`: stores upload or authorized YouTube source.
- `VideoMetadataService`: FFprobe metadata.
- `AudioExtractionService`: FFmpeg audio extraction, no-audio detection.
- `RealAiProvider`: real transcription orchestration.
- `TranscriptIntelligenceService`: niche, clip ranking, copy pack from real transcript.
- `ClipGenerationService`: stores clips/subtitles.
- `FfmpegVideoRenderer`: final 9:16 render.
- `TrendManager`: trend provider selection.

## Queue architecture

- `AnalyzeVideoJob`: transcribe + analyze + generate clips.
- `RenderClipJob`: render selected clip.

Production:

```bash
php artisan queue:work redis --queue=analysis,rendering,default --timeout=7200 --tries=3
```

## Data truth policy

- V7 default does not generate dummy transcripts.
- Audio videos require OpenAI/local Whisper.
- Text intelligence uses real transcript; if OpenAI text model is not configured, deterministic fallback is still based on real transcript/metadata.
- TikTok/Google trend adapters intentionally throw until connected to verified trend data.
