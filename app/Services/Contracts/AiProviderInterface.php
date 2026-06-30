<?php

namespace App\Services\Contracts;

use App\Models\Clip;
use App\Models\UploadedVideo;
use App\Models\VideoAnalysis;

interface AiProviderInterface
{
    public function transcribe(UploadedVideo $video): array;
    public function classifyVideo(UploadedVideo $video, array $transcript): array;
    public function detectClipCandidates(VideoAnalysis $analysis): array;
    public function generateSubtitleSegments(Clip $clip, array $transcriptSegments = []): array;
    public function generateCopyPack(array $classification, array $transcript): array;
}
