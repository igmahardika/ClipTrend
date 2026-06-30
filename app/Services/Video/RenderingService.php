<?php

namespace App\Services\Video;

use App\Models\Clip;
use App\Models\RenderJob;
use App\Jobs\RenderClipJob;
use App\Services\ActivityLogger;

class RenderingService
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function enqueue(Clip $clip, array $payload): RenderJob
    {
        $renderJob = RenderJob::create([
            'project_id' => $clip->project_id,
            'clip_id' => $clip->id,
            'user_id' => $clip->project->user_id,
            'platform' => $payload['platform'],
            'preset' => [
                'title' => $payload['title'] ?? $clip->title,
                'caption' => $payload['caption'] ?? null,
                'hashtags' => $payload['hashtags'] ?? [],
                'hook_text' => $payload['hook_text'] ?? $clip->hook_text,
                'subtitle_segments' => $payload['subtitle_segments'] ?? null,
                'options' => $payload['options'] ?? [],
            ],
            'status' => 'pending',
            'progress' => 0,
        ]);

        RenderClipJob::dispatch($renderJob)->onQueue(config('cliptrend.render_queue'));
        $this->activity->log('render.enqueued', $renderJob, ['clip_id' => $clip->id]);

        return $renderJob;
    }
}
