<?php

namespace App\Services\AI;

use App\Models\Clip;
use App\Models\UploadedVideo;
use App\Models\VideoAnalysis;
use App\Services\Contracts\AiProviderInterface;

class DummyAiProvider implements AiProviderInterface
{
    public function transcribe(UploadedVideo $video): array
    {
        throw new \RuntimeException('Demo AI provider is disabled in V7. Use AI_PROVIDER=real with OPENAI_API_KEY or WHISPER_BIN.');
    }

    public function classifyVideo(UploadedVideo $video, array $transcript): array
    {
        throw new \RuntimeException('Demo AI provider is disabled in V7.');
    }

    public function detectClipCandidates(VideoAnalysis $analysis): array
    {
        throw new \RuntimeException('Demo AI provider is disabled in V7.');
    }

    public function generateSubtitleSegments(Clip $clip, array $transcriptSegments = []): array
    {
        return [];
    }

    public function generateCopyPack(array $classification, array $transcript): array
    {
        throw new \RuntimeException('Demo AI provider is disabled in V7.');
    }
}
