<?php

namespace App\Services\Video;

use App\Models\UploadedVideo;
use App\Services\Storage\MediaPathService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class VideoNormalizationService
{
    public function __construct(
        private readonly MediaPathService $paths,
        private readonly VideoMetadataService $metadata,
    ) {}

    public function normalize(UploadedVideo $video): UploadedVideo
    {
        $video->loadMissing('project');
        $disk = Storage::disk(config('cliptrend.media_disk'));
        $source = $disk->path($video->path);

        if (! file_exists($source)) {
            throw new \RuntimeException('Original uploaded file is missing: '.$video->path);
        }

        $sourceMeta = $this->metadata->inspect($source);
        if (! ($sourceMeta['has_video'] ?? false)) {
            throw new \RuntimeException('Uploaded file does not contain a readable video stream. Please upload a valid video file supported by FFmpeg.');
        }

        $normalizedPath = $this->normalizedPath($video);
        $normalizedAbs = $disk->path($normalizedPath);
        @mkdir(dirname($normalizedAbs), 0775, true);

        $ffmpeg = config('cliptrend.ffmpeg_path', 'ffmpeg');
        $process = new Process([
            $ffmpeg,
            '-hide_banner',
            '-y',
            '-i', $source,
            '-map', '0:v:0',
            '-map', '0:a:0?',
            '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2,fps=30,setsar=1',
            '-c:v', 'libx264',
            '-preset', config('cliptrend.normalize_preset', 'veryfast'),
            '-crf', (string) config('cliptrend.normalize_crf', 20),
            '-pix_fmt', 'yuv420p',
            '-c:a', 'aac',
            '-b:a', '160k',
            '-ar', '44100',
            '-ac', '2',
            '-movflags', '+faststart',
            '-max_muxing_queue_size', '2048',
            $normalizedAbs,
        ]);
        $process->setTimeout((int) config('cliptrend.normalize_timeout', 3600));
        $process->run();

        if (! $process->isSuccessful() || ! file_exists($normalizedAbs) || filesize($normalizedAbs) < 1024) {
            Log::error('Video normalization failed', [
                'uploaded_video_id' => $video->id,
                'stderr' => $process->getErrorOutput(),
                'stdout' => $process->getOutput(),
            ]);
            throw new \RuntimeException('Video normalization failed: '.($process->getErrorOutput() ?: $process->getOutput()));
        }

        $normalizedMeta = $this->metadata->inspect($normalizedAbs);
        $thumbnailPath = $this->createThumbnail($video, $normalizedAbs);

        $meta = $video->metadata ?? [];
        $meta['_cliptrend'] = array_merge($meta['_cliptrend'] ?? [], [
            'original_metadata' => Arr::except($sourceMeta, ['metadata']),
            'normalized_path' => $normalizedPath,
            'normalized_size_bytes' => filesize($normalizedAbs),
            'normalized_metadata' => Arr::except($normalizedMeta, ['metadata']),
            'thumbnail_path' => $thumbnailPath,
            'normalized_at' => now()->toISOString(),
        ]);

        $video->forceFill([
            'duration_seconds' => $normalizedMeta['duration_seconds'] ?? $video->duration_seconds,
            'width' => $normalizedMeta['width'] ?? $video->width,
            'height' => $normalizedMeta['height'] ?? $video->height,
            'frame_rate' => $normalizedMeta['frame_rate'] ?? $video->frame_rate,
            'bitrate' => $normalizedMeta['bitrate'] ?? $video->bitrate,
            'metadata' => $meta,
            'status' => 'normalized',
        ])->save();

        return $video->fresh();
    }

    private function normalizedPath(UploadedVideo $video): string
    {
        return $this->paths->projectBase($video->project).'/normalized/source-'.$video->id.'.mp4';
    }

    private function createThumbnail(UploadedVideo $video, string $normalizedAbs): ?string
    {
        $disk = Storage::disk(config('cliptrend.media_disk'));
        $path = $this->paths->projectBase($video->project).'/thumbnails/source-'.$video->id.'.jpg';
        $abs = $disk->path($path);
        @mkdir(dirname($abs), 0775, true);

        $process = new Process([
            config('cliptrend.ffmpeg_path', 'ffmpeg'),
            '-hide_banner',
            '-y',
            '-ss', '1',
            '-i', $normalizedAbs,
            '-frames:v', '1',
            '-q:v', '2',
            $abs,
        ]);
        $process->setTimeout(120);
        $process->run();

        return $process->isSuccessful() && file_exists($abs) ? $path : null;
    }
}
