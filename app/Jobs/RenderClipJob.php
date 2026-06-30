<?php

namespace App\Jobs;

use App\Models\RenderJob as RenderJobModel;
use App\Services\Contracts\VideoRendererInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RenderClipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600;
    public ?int $userId = null;

    public function __construct(public RenderJobModel $renderJob)
    {
        $this->userId = $renderJob->user_id;
    }

    public function middleware(): array
    {
        if (config('queue.default') === 'sync') {
            return [];
        }

        return [new RateLimited('renders')];
    }

    public function handle(VideoRendererInterface $renderer): void
    {
        $this->renderJob->update([
            'status' => 'processing',
            'progress' => max(1, (int) $this->renderJob->progress),
            'attempts' => $this->renderJob->attempts + 1,
            'started_at' => now(),
        ]);

        $renderer->render($this->renderJob->fresh(['clip.subtitle', 'clip.project.uploadedVideo']));

        $this->renderJob->update([
            'status' => 'completed',
            'progress' => 100,
            'completed_at' => now(),
            'error_message' => null,
        ]);

        $this->renderJob->clip->update(['status' => 'rendered']);
        $this->renderJob->project->update(['status' => 'completed', 'render_status' => 'completed']);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Render job failed', [
            'render_job_id' => $this->renderJob->id,
            'error' => $exception->getMessage(),
        ]);

        $this->renderJob->update([
            'status' => 'failed',
            'progress' => 0,
            'error_message' => $exception->getMessage(),
        ]);
        $this->renderJob->project->update(['render_status' => 'failed']);
    }
}
