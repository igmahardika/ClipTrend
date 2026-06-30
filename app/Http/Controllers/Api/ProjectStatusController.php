<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RenderJob;
use App\Models\VideoProject;
use Illuminate\Http\JsonResponse;

class ProjectStatusController extends Controller
{
    public function project(VideoProject $project): JsonResponse
    {
        $this->authorize('view', $project);
        $project->load(['uploadedVideo', 'analyses.detectedNiche', 'clips.subtitle', 'renderJobs.renderedVideo']);

        return response()->json([
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'status' => $project->status,
                'niche_detection_status' => $project->niche_detection_status,
                'render_status' => $project->render_status,
                'duration_seconds' => $project->total_duration_seconds,
            ],
            'video' => $project->uploadedVideo ? [
                'id' => $project->uploadedVideo->id,
                'status' => $project->uploadedVideo->status,
                'duration_seconds' => $project->uploadedVideo->duration_seconds,
                'width' => $project->uploadedVideo->width,
                'height' => $project->uploadedVideo->height,
                'normalized' => (bool) data_get($project->uploadedVideo->metadata, '_cliptrend.normalized_path'),
            ] : null,
            'analysis' => $project->analyses->last()?->only(['id', 'summary', 'main_topic', 'content_style', 'ai_confidence', 'reasoning']),
            'clips' => $project->clips->map(fn ($clip) => [
                'id' => $clip->id,
                'title' => $clip->title,
                'start_time' => $clip->start_time,
                'end_time' => $clip->end_time,
                'duration_seconds' => $clip->duration_seconds,
                'viral_score' => $clip->viral_score,
                'retention_score' => $clip->retention_score,
                'subtitle_segments' => count($clip->subtitle?->segments ?? []),
            ])->values(),
            'render_jobs' => $project->renderJobs->map(fn ($job) => [
                'id' => $job->id,
                'clip_id' => $job->clip_id,
                'platform' => $job->platform,
                'status' => $job->status,
                'progress' => $job->progress,
                'error_message' => $job->error_message,
                'output_ready' => (bool) $job->renderedVideo,
            ])->values(),
        ]);
    }

    public function renderJob(RenderJob $renderJob): JsonResponse
    {
        $this->authorize('view', $renderJob->project);
        $renderJob->load(['clip', 'renderedVideo']);

        return response()->json([
            'id' => $renderJob->id,
            'project_id' => $renderJob->project_id,
            'clip_id' => $renderJob->clip_id,
            'platform' => $renderJob->platform,
            'status' => $renderJob->status,
            'progress' => $renderJob->progress,
            'error_message' => $renderJob->error_message,
            'output' => $renderJob->renderedVideo ? [
                'id' => $renderJob->renderedVideo->id,
                'duration_seconds' => $renderJob->renderedVideo->duration_seconds,
                'width' => $renderJob->renderedVideo->width,
                'height' => $renderJob->renderedVideo->height,
                'size_bytes' => $renderJob->renderedVideo->size_bytes,
                'status' => $renderJob->renderedVideo->status,
            ] : null,
        ]);
    }
}
