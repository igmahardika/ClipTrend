<?php

namespace App\Services\Video;

use App\Models\AiRecommendation;
use App\Models\DetectedNiche;
use App\Models\UploadedVideo;
use App\Models\VideoAnalysis;
use App\Services\AI\AiManager;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class VideoAnalysisService
{
    public function __construct(
        private readonly AiManager $ai,
        private readonly ActivityLogger $activity,
    ) {}

    public function analyze(UploadedVideo $video): VideoAnalysis
    {
        $provider = $this->ai->provider();
        $transcript = $provider->transcribe($video);
        $classification = $provider->classifyVideo($video, $transcript);
        $copy = $provider->generateCopyPack($classification, $transcript);

        return DB::transaction(function () use ($video, $transcript, $classification, $copy) {
            DetectedNiche::where('project_id', $video->project_id)->delete();
            $primaryNiche = null;

            foreach (($classification['niches'] ?? []) as $index => $niche) {
                $created = DetectedNiche::create([
                    'project_id' => $video->project_id,
                    'name' => $niche['name'],
                    'slug' => str($niche['name'])->slug(),
                    'confidence_score' => $niche['confidence_score'] ?? 0,
                    'reasoning' => $niche['reason'] ?? null,
                    'signals' => $niche['signals'] ?? [],
                    'is_primary' => $index === 0 || (bool) ($niche['is_primary'] ?? false),
                ]);
                $primaryNiche ??= $created;
            }

            $analysis = VideoAnalysis::updateOrCreate(
                ['uploaded_video_id' => $video->id],
                [
                    'project_id' => $video->project_id,
                    'detected_niche_id' => $primaryNiche?->id,
                    'summary' => $classification['summary'] ?? null,
                    'main_topic' => $classification['main_topic'] ?? null,
                    'audience_profile' => $classification['audience'] ?? [],
                    'content_style' => $classification['content_style'] ?? null,
                    'recommended_output' => $classification['recommended_platforms'] ?? [],
                    'recommended_duration_seconds' => $classification['recommended_durations'] ?? [],
                    'ai_confidence' => $classification['confidence'] ?? ($primaryNiche?->confidence_score ?? 0),
                    'reasoning' => $classification['reasoning'] ?? $primaryNiche?->reasoning,
                    'raw_payload' => [
                        'classification' => $classification,
                        'transcript' => $transcript,
                    ],
                    'analyzed_at' => now(),
                ]
            );

            AiRecommendation::where('project_id', $video->project_id)->where('type', 'copy_pack')->delete();
            AiRecommendation::create([
                'project_id' => $video->project_id,
                'type' => 'copy_pack',
                'title' => $copy['title'] ?? null,
                'content' => $copy,
                'confidence_score' => $classification['viral_score'] ?? 0,
                'raw_payload' => $copy,
            ]);

            $video->update(['status' => 'analyzed']);
            $video->project->update(['status' => 'analyzed', 'niche_detection_status' => 'completed']);
            $this->activity->log('video.analyzed', $video, ['analysis_id' => $analysis->id]);

            return $analysis->fresh(['detectedNiche', 'project.detectedNiches']);
        });
    }
}
