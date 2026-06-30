<?php

namespace App\Services\Video;

use App\Models\UploadedVideo;
use App\Models\VideoProject;
use App\Services\Storage\MediaPathService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class VideoIngestionService
{
    public function __construct(
        private readonly MediaPathService $paths,
        private readonly VideoMetadataService $metadata,
        private readonly VideoNormalizationService $normalizer,
    ) {}

    public function storeUpload(VideoProject $project, UploadedFile $file): UploadedVideo
    {
        return DB::transaction(function () use ($project, $file) {
            $this->purgeProjectMedia($project);

            $extension = strtolower($file->getClientOriginalExtension() ?: 'mp4');
            $path = $this->paths->uploads($project, $extension);
            $disk = Storage::disk(config('cliptrend.media_disk'));
            $disk->putFileAs(dirname($path), $file, basename($path));

            $absolutePath = $disk->path($path);
            $metadata = $this->metadata->inspect($absolutePath);

            if (! ($metadata['has_video'] ?? false)) {
                Storage::disk(config('cliptrend.media_disk'))->delete($path);
                throw new \RuntimeException('File yang diupload tidak memiliki stream video yang valid. Gunakan MP4/MOV/MKV/WebM/AVI yang dapat dibaca FFmpeg.');
            }

            $video = UploadedVideo::create([
                'project_id' => $project->id,
                'disk' => config('cliptrend.media_disk'),
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'video/mp4',
                'extension' => $extension,
                'size_bytes' => $file->getSize(),
                'duration_seconds' => $metadata['duration_seconds'],
                'width' => $metadata['width'],
                'height' => $metadata['height'],
                'frame_rate' => $metadata['frame_rate'] ?? null,
                'bitrate' => $metadata['bitrate'] ?? null,
                'checksum' => hash_file('sha256', $absolutePath),
                'status' => 'uploaded',
                'metadata' => array_merge($metadata['metadata'] ?? [], [
                    '_cliptrend' => [
                        'original_upload_path' => $path,
                        'original_format' => $extension,
                    ],
                ]),
            ]);

            if ((bool) config('cliptrend.normalize_on_upload', true)) {
                $video = $this->normalizer->normalize($video);
            }

            $project->update([
                'source_type' => 'upload',
                'youtube_url' => null,
                'status' => 'uploaded',
                'niche_detection_status' => 'pending',
                'render_status' => 'not_started',
                'total_duration_seconds' => $video->duration_seconds,
            ]);

            return $video;
        });
    }

    public function registerYouTube(VideoProject $project, string $url): UploadedVideo
    {
        if (config('cliptrend.youtube_ingestion_enabled')) {
            return $this->downloadAuthorizedYouTubeSource($project, $url);
        }

        return DB::transaction(function () use ($project, $url) {
            $this->purgeProjectMedia($project);

            $project->update([
                'source_type' => 'youtube',
                'youtube_url' => $url,
                'status' => 'pending_ingest',
                'niche_detection_status' => 'pending',
                'render_status' => 'not_started',
            ]);

            return UploadedVideo::create([
                'project_id' => $project->id,
                'disk' => config('cliptrend.media_disk'),
                'path' => 'external/youtube/'.md5($url).'.pending',
                'original_filename' => 'authorized-youtube-source',
                'mime_type' => null,
                'extension' => null,
                'size_bytes' => 0,
                'status' => 'pending_ingest',
                'metadata' => [
                    'source_url' => $url,
                    'notice' => 'YouTube ingestion is disabled. Set YOUTUBE_INGESTION_ENABLED=true and install yt-dlp for owned/authorized sources.',
                ],
            ]);
        });
    }

    private function downloadAuthorizedYouTubeSource(VideoProject $project, string $url): UploadedVideo
    {
        return DB::transaction(function () use ($project, $url) {
            $this->purgeProjectMedia($project);

            $path = $this->paths->uploads($project, 'mp4');
            $disk = Storage::disk(config('cliptrend.media_disk'));
            $absolutePath = $disk->path($path);
            @mkdir(dirname($absolutePath), 0775, true);

            $process = new Process([
                config('cliptrend.ytdlp_path', 'yt-dlp'),
                '--no-playlist',
                '--merge-output-format', 'mp4',
                '-f', 'bv*[ext=mp4]+ba[ext=m4a]/b[ext=mp4]/best',
                '-o', $absolutePath,
                $url,
            ]);
            $process->setTimeout(3600);
            $process->run();

            if (! $process->isSuccessful() || ! file_exists($absolutePath)) {
                throw new \RuntimeException('Authorized YouTube ingestion failed: '.($process->getErrorOutput() ?: $process->getOutput()));
            }

            $metadata = $this->metadata->inspect($absolutePath);
            if (! ($metadata['has_video'] ?? false)) {
                throw new \RuntimeException('Downloaded authorized YouTube source does not contain a readable video stream.');
            }

            $video = UploadedVideo::create([
                'project_id' => $project->id,
                'disk' => config('cliptrend.media_disk'),
                'path' => $path,
                'original_filename' => 'authorized-youtube-source.mp4',
                'mime_type' => 'video/mp4',
                'extension' => 'mp4',
                'size_bytes' => filesize($absolutePath),
                'duration_seconds' => $metadata['duration_seconds'],
                'width' => $metadata['width'],
                'height' => $metadata['height'],
                'frame_rate' => $metadata['frame_rate'] ?? null,
                'bitrate' => $metadata['bitrate'] ?? null,
                'checksum' => hash_file('sha256', $absolutePath),
                'status' => 'uploaded',
                'metadata' => array_merge($metadata['metadata'] ?? [], [
                    '_cliptrend' => ['original_upload_path' => $path],
                    'source_url' => $url,
                    'ingestion' => 'yt-dlp',
                    'legal_notice' => 'Use only for owned or explicitly authorized content.',
                ]),
            ]);

            if ((bool) config('cliptrend.normalize_on_upload', true)) {
                $video = $this->normalizer->normalize($video);
            }

            $project->update([
                'source_type' => 'youtube',
                'youtube_url' => $url,
                'status' => 'uploaded',
                'niche_detection_status' => 'pending',
                'render_status' => 'not_started',
                'total_duration_seconds' => $video->duration_seconds,
            ]);

            return $video;
        });
    }

    private function purgeProjectMedia(VideoProject $project): void
    {
        $diskName = config('cliptrend.media_disk');
        $disk = Storage::disk($diskName);

        $project->loadMissing(['uploadedVideo', 'renderedVideos']);

        foreach ($project->renderedVideos as $rendered) {
            if ($rendered->disk === $diskName && $rendered->path && $disk->exists($rendered->path)) {
                $disk->delete($rendered->path);
            }
        }

        $uploadedVideos = UploadedVideo::where('project_id', $project->id)->get();
        foreach ($uploadedVideos as $video) {
            $meta = $video->metadata ?? [];
            foreach ([$video->path, $meta['_cliptrend']['normalized_path'] ?? null, $meta['_cliptrend']['thumbnail_path'] ?? null, $meta['analysis_audio_path'] ?? null] as $path) {
                if (is_string($path) && $path !== '' && ! str_ends_with($path, '.pending') && $disk->exists($path)) {
                    $disk->delete($path);
                }
            }
        }

        $project->renderedVideos()->delete();
        $project->renderJobs()->delete();
        $project->clips()->delete();
        $project->analyses()->delete();
        $project->detectedNiches()->delete();
        $project->aiRecommendations()->delete();
        UploadedVideo::where('project_id', $project->id)->delete();
    }
}
