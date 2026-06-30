<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateTrendsCommand extends Command
{
    protected $signature = 'cliptrend:update-trends';
    protected $description = 'Updates short-form video niche and trend data weekly using Google Gemini API';

    public function handle(): int
    {
        $this->info('Starting weekly short-form trends update...');

        $apiKey = config('cliptrend.openai.api_key') ?: env('OPENAI_API_KEY');
        if (! $apiKey) {
            $this->error('Error: API key is not configured.');
            return 1;
        }

        // Use gemini model since API key is Gemini
        $model = 'gemini-2.0-flash';
        $prompt = $this->buildPrompt();

        $this->info('Fetching latest trends from Gemini...');

        try {
            $response = Http::timeout(60)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature' => 0.4
                    ]
                ]
            );

            if (! $response->successful()) {
                $this->error('Gemini API call failed: ' . $response->body());
                return 1;
            }

            $payload = $response->json();
            $text = $payload['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (! is_string($text) || trim($text) === '') {
                $this->error('Empty response from Gemini.');
                return 1;
            }

            $json = json_decode($text, true);
            if (! is_array($json) || ! isset($json['niches']) || ! isset($json['platforms'])) {
                $this->error('Invalid JSON structure returned by Gemini: ' . $text);
                return 1;
            }

            $json['last_updated_at'] = now()->toIso8601String();

            // Save to storage
            $path = storage_path('app/trends.json');
            file_put_contents($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $this->info("Successfully updated trends and saved to {$path}");
            Log::info('ClipTrend weekly short-form trends database updated successfully.');
            return 0;

        } catch (\Throwable $e) {
            $this->error('Exception occurred: ' . $e->getMessage());
            Log::error('Weekly trends update failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }

    private function buildPrompt(): string
    {
        return <<<PROMPT
You are a short-form video trend intelligence system.
Analyze the latest web trends for YouTube Shorts, TikTok, and Instagram Reels this week.
Generate the trending topics, keywords, tags, hooks, and captions that are performing exceptionally well in Indonesia right now.
Provide the output in English or Indonesian as relevant for the fields.

You MUST return a JSON object conforming exactly to this structure:
{
  "niches": {
    "Niche Name (e.g. Sepak Bola, Podcast Motivasi, Edukasi Bisnis, Tutorial, Event / Konser, Review Produk, Kuliner, Travel)": {
      "style": "Brief description of visual style",
      "audience": {
        "primary": "Target audience age and background",
        "intent": "What they are looking for in these videos",
        "language": "Usually Indonesia or English"
      },
      "durations": {"shorts": 45, "tiktok": 35, "reels": 30},
      "platforms": ["tiktok", "shorts", "reels"],
      "terms": ["keyword1", "keyword2", "keyword3", "englishKeyword1", "englishKeyword2"... (at least 15 keywords that might appear in transcripts)],
      "hashtags": ["#tag1", "#tag2", "#tag3"... (at least 6 relevant hashtags)]
    }
  },
  "platforms": {
    "tiktok": {
      "trending_hashtags": ["#tag1", "#tag2"...],
      "hooks": ["Engaging hook line 1", "Engaging hook line 2"...],
      "angles": ["Creative angle description 1", "Creative angle description 2"...],
      "captions": ["Caption option 1", "Caption option 2"...]
    },
    "shorts": {
      "trending_hashtags": ["#tag1", "#tag2"...],
      "hooks": ["Engaging hook line 1", "Engaging hook line 2"...],
      "angles": ["Creative angle description 1", "Creative angle description 2"...],
      "captions": ["Caption option 1", "Caption option 2"...]
    },
    "reels": {
      "trending_hashtags": ["#tag1", "#tag2"...],
      "hooks": ["Engaging hook line 1", "Engaging hook line 2"...],
      "angles": ["Creative angle description 1", "Creative angle description 2"...],
      "captions": ["Caption option 1", "Caption option 2"...]
    }
  }
}

Do not include any markdown format tags like ```json or trailing text. Return ONLY the raw JSON.
PROMPT;
    }
}
