<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('cliptrend:health', function () {
    $this->info('ClipTrend AI is ready.');
});

Schedule::command('cliptrend:update-trends')->weekly();
