<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('cliptrend:health', function () {
    $this->info('ClipTrend AI is ready.');
});
