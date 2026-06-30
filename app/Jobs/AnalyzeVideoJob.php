<?php

namespace App\Jobs;

use App\Models\UploadedVideo;
use App\Services\Video\ClipGenerationService;
use App\Services\Video\VideoAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1800;
    public ?int $userId = null;

    public function __construct(public UploadedVideo $uploadedVideo)
    {
        $this->userId = $uploadedVideo->project?->user_id;
    }

    public function middleware(): array
    {
        if (config('queue.default') === 'sync') {
            return [];
        }

        return [new RateLimited('analysis')];
    }

    public function handle(VideoAnalysisService $analysis, ClipGenerationService $clips): void
    {
        $video = $this->uploadedVideo->fresh(['project']);

        if (! $video->isReadyForAnalysis()) {
            return;
        }

        $video->update(['status' => 'analyzing']);
        $video->project->update(['status' => 'analyzing', 'niche_detection_status' => 'processing']);

        $result = $analysis->analyze($video);
        $clips->generate($result);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Video analysis failed', [
            'uploaded_video_id' => $this->uploadedVideo->id,
            'error' => $exception->getMessage(),
        ]);

        $video = $this->uploadedVideo->fresh(['project']);
        if (! $video) {
            return;
        }

        $video->update([
            'status' => 'failed',
            'metadata' => array_merge($video->metadata ?? [], ['analysis_error' => $exception->getMessage()]),
        ]);
        $video->project?->update(['status' => 'failed', 'niche_detection_status' => 'failed']);
    }
}
