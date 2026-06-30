<?php

namespace App\Services\Video;

use App\Models\UploadedVideo;
use Illuminate\Support\Facades\Storage;

class WorkingMediaResolver
{
    public function relativePath(UploadedVideo $video): string
    {
        $meta = $video->metadata ?? [];
        $path = $meta['_cliptrend']['normalized_path']
            ?? $meta['normalized_path']
            ?? $video->path;

        if (! is_string($path) || trim($path) === '') {
            throw new \RuntimeException('No working media path is available for uploaded video '.$video->id.'.');
        }

        return $path;
    }

    public function absolutePath(UploadedVideo $video): string
    {
        $disk = Storage::disk(config('cliptrend.media_disk'));
        $path = $this->relativePath($video);
        $absolute = $disk->path($path);

        if (! file_exists($absolute)) {
            if ($video->status === 'pending_ingest' || str_ends_with($path, '.pending')) {
                throw new \RuntimeException(
                    'Video belum tersedia. Aktifkan YOUTUBE_INGESTION_ENABLED=true dan pasang yt-dlp, atau upload file video langsung ke project ini.'
                );
            }

            throw new \RuntimeException('Working media file is missing: '.$path);
        }

        return $absolute;
    }

    public function thumbnailRelativePath(UploadedVideo $video): ?string
    {
        $meta = $video->metadata ?? [];
        $path = $meta['_cliptrend']['thumbnail_path'] ?? $meta['thumbnail_path'] ?? null;
        return is_string($path) && $path !== '' ? $path : null;
    }
}
