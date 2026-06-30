<?php

namespace App\Services\Trends;

use App\Services\Contracts\TrendProviderInterface;

class TikTokCreativeCenterProvider implements TrendProviderInterface
{
    public function analyze(array $filters): array
    {
        throw new \RuntimeException('TikTok trend provider belum dikonfigurasi. Gunakan TREND_PROVIDER=local atau youtube dengan YOUTUBE_DATA_API_KEY.');
    }
}
