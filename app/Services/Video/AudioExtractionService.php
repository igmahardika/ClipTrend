<?php

namespace App\Services\Video;

use App\Models\UploadedVideo;
use App\Services\Storage\MediaPathService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class AudioExtractionService
{
    public function __construct(
        private readonly MediaPathService $paths,
        private readonly WorkingMediaResolver $resolver,
    ) {}

    public function extractWav(UploadedVideo $video): ?array
    {
        $video->loadMissing('project');

        $disk = Storage::disk(config('cliptrend.media_disk'));
        $input = $this->resolver->absolutePath($video);

        if (! $this->hasAudioStream($video)) {
            $metadata = $video->metadata ?? [];
            $metadata['analysis_audio_status'] = 'no_audio_stream_detected';
            $metadata['analysis_audio_extracted_at'] = now()->toISOString();
            $video->forceFill(['metadata' => $metadata])->save();
            return null;
        }

        $audioPath = $this->paths->audio($video);
        $audioAbsolutePath = $disk->path($audioPath);
        @mkdir(dirname($audioAbsolutePath), 0775, true);

        $process = new Process([
            config('cliptrend.ffmpeg_path', 'ffmpeg'),
            '-y',
            '-i', $input,
            '-map', '0:a:0?',
            '-vn',
            '-acodec', 'pcm_s16le',
            '-ar', '16000',
            '-ac', '1',
            $audioAbsolutePath,
        ]);
        $process->setTimeout((int) config('cliptrend.analysis_timeout', 1800));
        $process->run();

        if (! $process->isSuccessful() || ! file_exists($audioAbsolutePath) || filesize($audioAbsolutePath) < 128) {
            throw new \RuntimeException('Audio extraction failed: '.($process->getErrorOutput() ?: $process->getOutput()));
        }

        $metadata = $video->metadata ?? [];
        $metadata['analysis_audio_path'] = $audioPath;
        $metadata['analysis_audio_size_bytes'] = filesize($audioAbsolutePath);
        $metadata['analysis_audio_status'] = 'extracted';
        $metadata['analysis_audio_extracted_at'] = now()->toISOString();
        $video->forceFill(['metadata' => $metadata])->save();

        return [
            'disk' => config('cliptrend.media_disk'),
            'path' => $audioPath,
            'absolute_path' => $audioAbsolutePath,
            'size_bytes' => filesize($audioAbsolutePath),
        ];
    }

    private function hasAudioStream(UploadedVideo $video): bool
    {
        $meta = $video->metadata ?? [];
        if (array_key_exists('has_audio', $meta)) {
            return (bool) $meta['has_audio'];
        }
        if (isset($meta['_cliptrend']['normalized_metadata']['has_audio'])) {
            return (bool) $meta['_cliptrend']['normalized_metadata']['has_audio'];
        }
        if (isset($meta['_cliptrend']['original_metadata']['has_audio'])) {
            return (bool) $meta['_cliptrend']['original_metadata']['has_audio'];
        }
        if (array_key_exists('_cliptrend', $meta) && array_key_exists('has_audio', $meta['_cliptrend'])) {
            return (bool) $meta['_cliptrend']['has_audio'];
        }
        if (isset($meta['streams']) && is_array($meta['streams'])) {
            foreach ($meta['streams'] as $stream) {
                if (($stream['codec_type'] ?? null) === 'audio') {
                    return true;
                }
            }
        }
        return true;
    }
}
