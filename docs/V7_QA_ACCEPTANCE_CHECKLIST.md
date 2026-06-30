# V7 QA Acceptance Checklist

A build can be called sell-ready only after this checklist passes in the developer/server environment.

## Environment

- [ ] `composer install` passes.
- [ ] `npm install` passes.
- [ ] `npm run build` passes.
- [ ] `php artisan migrate --seed` passes.
- [ ] `php artisan route:list` passes.
- [ ] `php artisan test` passes.
- [ ] `bash scripts/v7-smoke-check.sh` passes.
- [ ] FFmpeg and FFprobe path valid.
- [ ] OpenAI API key or local Whisper configured.
- [ ] Queue worker running.

## Upload Matrix

Test at least:

- [ ] Landscape MP4 with audio.
- [ ] Portrait MP4 with audio.
- [ ] Square MP4 with audio.
- [ ] MOV from iPhone.
- [ ] MKV/WebM sample.
- [ ] Video without audio.
- [ ] Corrupt/non-video file rejected clearly.
- [ ] Large file test according to chosen upload limit.

## Analysis

- [ ] Audio extracted to WAV.
- [ ] Transcription created from real audio.
- [ ] Analysis fails clearly if transcriber missing.
- [ ] Niche detected from transcript/metadata.
- [ ] At least 3 clips generated for long video.
- [ ] Clip timestamps are within real video duration.
- [ ] Subtitle segments are aligned to clip duration.
- [ ] Copy pack uses transcript/topic, not hardcoded dummy text.

## Editor

- [ ] User can preview source video.
- [ ] User can review niche and clip scores.
- [ ] User can edit subtitle text.
- [ ] User can edit hook/caption/hashtags before render.
- [ ] User can select platform output.

## Rendering

- [ ] Render job created.
- [ ] Queue processes render.
- [ ] Progress updates.
- [ ] Failed job shows error and retry button.
- [ ] Output is 1080x1920.
- [ ] Output has audio when source has audio.
- [ ] Output works without source audio.
- [ ] Subtitle/hook/progress bar visible.
- [ ] MP4 plays in browser.
- [ ] Download works.

## Security

- [ ] User cannot access project owned by another user.
- [ ] Private media not exposed from public folder.
- [ ] Download/stream routes enforce policy.
- [ ] Upload limits enforced.
- [ ] `.env` not committed.
- [ ] API keys not stored in database or frontend.

## Production

- [ ] Supervisor/systemd queue worker configured.
- [ ] Storage backups configured.
- [ ] Log rotation configured.
- [ ] Error monitoring configured.
- [ ] Rate limiting configured.
- [ ] Terms/legal notice for YouTube authorized content reviewed.
