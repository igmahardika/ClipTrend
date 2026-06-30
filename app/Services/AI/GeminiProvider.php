<?php

namespace App\Services\AI;

use App\Models\Clip;
use App\Models\UploadedVideo;
use App\Models\VideoAnalysis;
use App\Services\Contracts\AiProviderInterface;

/**
 * GeminiProvider — delegates to RealAiProvider which auto-detects Gemini API keys
 * (keys starting with AQ. or AIzaSy) and routes to the Gemini REST API.
 *
 * Set AI_PROVIDER=gemini or AI_PROVIDER=real — both work identically when
 * OPENAI_API_KEY is a Gemini key, because RealAiProvider detects the key format.
 */
class GeminiProvider implements AiProviderInterface
{
    public function __construct(private readonly RealAiProvider $real) {}

    public function transcribe(UploadedVideo $video): array
    {
        return $this->real->transcribe($video);
    }

    public function classifyVideo(UploadedVideo $video, array $transcript): array
    {
        return $this->real->classifyVideo($video, $transcript);
    }

    public function detectClipCandidates(VideoAnalysis $analysis): array
    {
        return $this->real->detectClipCandidates($analysis);
    }

    public function generateSubtitleSegments(Clip $clip, array $transcriptSegments = []): array
    {
        return $this->real->generateSubtitleSegments($clip, $transcriptSegments);
    }

    public function generateCopyPack(array $classification, array $transcript): array
    {
        return $this->real->generateCopyPack($classification, $transcript);
    }
}
