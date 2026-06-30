<?php

namespace App\Services\Trends;

use App\Services\Contracts\TrendProviderInterface;

class DummyTrendProvider implements TrendProviderInterface
{
    public function analyze(array $filters): array
    {
        return app(LocalTrendProvider::class)->analyze($filters);
    }
}
