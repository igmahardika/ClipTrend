# QA Checklist — ClipTrend AI V7

## Environment

- [ ] `composer install` succeeds.
- [ ] `npm install` succeeds.
- [ ] `npm run build` succeeds.
- [ ] `php artisan migrate --seed` succeeds.
- [ ] `php artisan route:list` succeeds.
- [ ] `ffmpeg` and `ffprobe` are installed.
- [ ] `scripts/v7-smoke-check.sh` passes.

## Real analysis

- [ ] Upload landscape video with audio.
- [ ] Metadata shows duration, resolution, codec, has_audio.
- [ ] Audio WAV is extracted.
- [ ] Transcript is generated via OpenAI/local Whisper.
- [ ] Detected niche appears with confidence and reasoning.
- [ ] Clip candidates use real timestamps from transcript.
- [ ] Subtitles are generated and editable.
- [ ] Copy pack title/caption/hashtag appears.

## Render

- [ ] Render `fit_blur` landscape video.
- [ ] Render portrait video.
- [ ] Render square video.
- [ ] Render video without audio.
- [ ] Output resolution is 1080x1920.
- [ ] Output codec is H.264/AAC where audio exists.
- [ ] Subtitle is burned in.
- [ ] Hook appears at start.
- [ ] Progress bar appears.
- [ ] Download works.

## Security

- [ ] User cannot view project owned by another user.
- [ ] User cannot stream/download source owned by another user.
- [ ] Upload size limit is enforced.
- [ ] Invalid file upload fails.
- [ ] `.env` is not committed.

## Failure behavior

- [ ] Audio video without transcriber fails with clear error.
- [ ] FFmpeg failure records render error.
- [ ] Retry failed render works.
- [ ] Trend checker fails clearly when YouTube API key is missing.
