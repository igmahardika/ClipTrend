<?php

namespace App\Services\Trends;

use App\Services\Contracts\TrendProviderInterface;

class TrendManager
{
    public function provider(): TrendProviderInterface
    {
        $provider = config('cliptrend.trends.provider');

        if (in_array($provider, ['youtube', 'real'], true) && ! env('YOUTUBE_DATA_API_KEY')) {
            return app(LocalTrendProvider::class);
        }

        return match ($provider) {
            'youtube', 'real' => app(YouTubeTrendProvider::class),
            'local', 'offline' => app(LocalTrendProvider::class),
            'tiktok' => app(TikTokCreativeCenterProvider::class),
            'google_trends' => app(GoogleTrendsProvider::class),
            'dummy', 'demo' => app(DummyTrendProvider::class),
            default => app(LocalTrendProvider::class),
        };
    }
}
