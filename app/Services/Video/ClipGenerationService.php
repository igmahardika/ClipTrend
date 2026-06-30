<?php

namespace App\Services\Video;

use App\Models\Clip;
use App\Models\Subtitle;
use App\Models\VideoAnalysis;
use App\Services\AI\AiManager;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class ClipGenerationService
{
    public function __construct(
        private readonly AiManager $ai,
        private readonly ActivityLogger $activity,
    ) {}

    public function generate(VideoAnalysis $analysis): array
    {
        $provider = $this->ai->provider();
        $candidates = $provider->detectClipCandidates($analysis);
        $transcriptSegments = $analysis->raw_payload['transcript']['segments'] ?? [];

        return DB::transaction(function () use ($analysis, $provider, $candidates, $transcriptSegments) {
            $analysis->project->clips()->delete();
            $clips = [];

            foreach ($candidates as $candidate) {
                $clip = Clip::create([
                    'project_id' => $analysis->project_id,
                    'uploaded_video_id' => $analysis->uploaded_video_id,
                    'title' => $candidate['title'],
                    'start_time' => $candidate['start_seconds'],
                    'end_time' => $candidate['end_seconds'],
                    'duration_seconds' => $candidate['duration_seconds'],
                    'hook_text' => $candidate['hook_text'],
                    'transcript_excerpt' => $candidate['description'] ?? null,
                    'retention_score' => $candidate['retention_score'] ?? 0,
                    'viral_score' => $candidate['viral_score'] ?? 0,
                    'platform_fit' => $candidate['recommended_platforms'] ?? ['shorts', 'tiktok', 'reels'],
                    'status' => 'candidate',
                ]);

                $segments = $provider->generateSubtitleSegments($clip, $transcriptSegments);
                Subtitle::create([
                    'project_id' => $analysis->project_id,
                    'clip_id' => $clip->id,
                    'language' => 'id',
                    'segments' => $segments,
                    'style' => config('cliptrend.default_subtitle_style'),
                    'status' => 'generated',
                ]);

                $clips[] = $clip->fresh('subtitle');
            }

            $analysis->project->update(['status' => 'clips_ready']);
            $this->activity->log('clips.generated', $analysis, ['count' => count($clips)]);

            return $clips;
        });
    }
}
