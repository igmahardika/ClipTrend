<?php

namespace App\Services\Contracts;

interface TrendProviderInterface
{
    public function analyze(array $filters): array;
}
