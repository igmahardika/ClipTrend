<?php

namespace App\Services\AI;

use App\Services\Contracts\AiProviderInterface;

class AiManager
{
    public function provider(): AiProviderInterface
    {
        return match (config('cliptrend.ai.provider')) {
            'real' => app(RealAiProvider::class),
            'openai' => app(OpenAiProvider::class),
            'ollama' => app(OllamaProvider::class),
            'gemini' => app(GeminiProvider::class),
            'dummy', 'demo' => app(DummyAiProvider::class),
            default => app(RealAiProvider::class),
        };
    }
}
