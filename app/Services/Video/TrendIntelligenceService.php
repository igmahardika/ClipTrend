<?php

namespace App\Services\Video;

use App\Models\TrendReport;
use App\Models\User;
use App\Services\Trends\TrendManager;

class TrendIntelligenceService
{
    public function __construct(private readonly TrendManager $trends) {}

    public function report(User $user, array $filters): TrendReport
    {
        $payload = $this->trends->provider()->analyze($filters);

        return TrendReport::create([
            'user_id' => $user->id,
            'project_id' => $filters['project_id'] ?? null,
            'niche' => $filters['niche'] ?? null,
            'topic' => $filters['topic'] ?? null,
            'platform' => $filters['platform'] ?? 'tiktok',
            'region' => $filters['region'] ?? 'ID',
            'hashtags' => $payload['hashtags'] ?? [],
            'angles' => $payload['angles'] ?? [],
            'hooks' => $payload['hooks'] ?? [],
            'captions' => $payload['captions'] ?? [],
            'score' => $payload['score'] ?? 0,
            'raw_payload' => $payload,
            'generated_at' => now(),
        ]);
    }
}
