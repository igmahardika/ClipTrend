<?php

namespace App\Services\Trends;

use App\Services\Contracts\TrendProviderInterface;

class GoogleTrendsProvider implements TrendProviderInterface
{
    public function analyze(array $filters): array
    {
        throw new \RuntimeException('Google Trends provider belum dikonfigurasi. Gunakan TREND_PROVIDER=local atau youtube dengan YOUTUBE_DATA_API_KEY.');
    }
}
