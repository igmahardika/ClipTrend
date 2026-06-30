<?php

namespace App\Services\Contracts;

use App\Models\RenderedVideo;
use App\Models\RenderJob;

interface VideoRendererInterface
{
    public function render(RenderJob $renderJob): RenderedVideo;
}
