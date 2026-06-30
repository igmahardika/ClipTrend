<?php

namespace App\Services\AI;

use App\Models\Clip;
use App\Models\UploadedVideo;
use App\Models\VideoAnalysis;
use App\Services\Contracts\AiProviderInterface;
use App\Services\Video\AudioExtractionService;
use App\Services\Video\VideoSignalService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class RealAiProvider implements AiProviderInterface
{
    public function __construct(
        private readonly AudioExtractionService $audio,
        private readonly VideoSignalService $signals,
        private readonly TranscriptIntelligenceService $intelligence,
    ) {}

    public function transcribe(UploadedVideo $video): array
    {
        $audio = $this->audio->extractWav($video);
        $transcript = null;

        if ($audio) {
            // Priority 1: Local Whisper (offline, free, no quota limit)
            $transcript = $this->transcribeWithLocalWhisper($audio['absolute_path']);

            // Priority 2: Gemini API (online, free tier with quota limits)
            if (! $transcript) {
                $transcript = $this->transcribeWithOpenAi($audio['absolute_path']);
            }

            if (! $transcript) {
                $transcript = [
                    'language' => config('cliptrend.transcription_language', 'id'),
                    'text' => '',
                    'segments' => [],
                    'provider' => 'visual_only_transcription_unavailable',
                    'raw_payload' => [
                        'notice' => 'Transcription skipped because WHISPER_BIN and OPENAI_API_KEY are not configured. Analysis continues using video metadata and scene signals.',
                    ],
                ];
            }
        } else {
            $transcript = [
                'language' => config('cliptrend.transcription_language', 'id'),
                'text' => '',
                'segments' => [],
                'provider' => 'visual_only_no_audio_stream',
                'raw_payload' => ['notice' => 'No audio stream detected. Analysis will use real video metadata and scene signals only.'],
            ];
        }

        $videoSignals = $this->signals->inspectSignals($video, $audio ? $audio['absolute_path'] : null);

        return array_merge($transcript, [
            'provider' => $transcript['provider'] ?? 'real',
            'audio' => $audio ? Arr::except($audio, ['absolute_path']) : null,
            'signals' => $videoSignals,
            'source' => $audio ? 'real_uploaded_video_audio_transcript' : 'real_uploaded_video_visual_only',
        ]);
    }

    public function classifyVideo(UploadedVideo $video, array $transcript): array
    {
        return $this->intelligence->classify($video, $transcript);
    }

    public function detectClipCandidates(VideoAnalysis $analysis): array
    {
        return $this->intelligence->detectClipCandidates($analysis);
    }

    public function generateCopyPack(array $classification, array $transcript): array
    {
        return $this->intelligence->generateCopyPack($classification, $transcript);
    }

    public function generateSubtitleSegments(Clip $clip, array $transcriptSegments = []): array
    {
        $segments = collect($transcriptSegments)
            ->filter(fn ($segment) => ($segment['end'] ?? 0) > $clip->start_time && ($segment['start'] ?? 0) < $clip->end_time)
            ->flatMap(fn ($segment) => $this->splitSubtitleSegment($segment, $clip))
            ->filter(fn ($segment) => trim($segment['text']) !== '' && $segment['end'] > $segment['start'])
            ->values()
            ->all();

        return $segments;
    }

    private function getApiKeys(): array
    {
        $keysStr = config('cliptrend.openai.api_key') ?: env('OPENAI_API_KEY') ?: '';
        $keys = array_filter(array_map('trim', explode(',', $keysStr)));
        return array_values($keys);
    }

    private function transcribeWithOpenAi(string $audioAbsolutePath): ?array
    {
        $apiKeys = $this->getApiKeys();
        if (empty($apiKeys)) {
            return null;
        }

        $lastException = null;
        foreach ($apiKeys as $index => $apiKey) {
            try {
                if (str_starts_with($apiKey, 'AIzaSy') || str_starts_with($apiKey, 'AQ.')) {
                    $res = $this->transcribeWithGemini($apiKey, $audioAbsolutePath);
                    if ($res) {
                        return $res;
                    }
                } else {
                    $response = Http::timeout((int) config('cliptrend.openai.timeout', 900))
                        ->withToken($apiKey)
                        ->attach('file', fopen($audioAbsolutePath, 'r'), basename($audioAbsolutePath))
                        ->post('https://api.openai.com/v1/audio/transcriptions', [
                            'model' => config('cliptrend.openai.transcription_model', 'whisper-1'),
                            'response_format' => 'verbose_json',
                            'language' => config('cliptrend.transcription_language', 'id'),
                            'timestamp_granularities[]' => 'segment',
                        ]);

                    if ($response->status() === 429) {
                        \Illuminate\Support\Facades\Log::warning("OpenAI Key #{$index} exhausted (429), rotating...");
                        continue;
                    }

                    if (! $response->successful()) {
                        throw new \RuntimeException('OpenAI transcription failed: '.$response->body());
                    }

                    return $this->normalizeTranscriptPayload($response->json() ?: [], 'openai');
                }
            } catch (\Throwable $e) {
                $lastException = $e;
                \Illuminate\Support\Facades\Log::warning("Transcription failed with key #{$index}: " . $e->getMessage());
            }
        }

        if ($lastException) {
            throw $lastException;
        }
        return null;
    }

    private function transcribeWithGemini(string $apiKey, string $audioAbsolutePath): ?array
    {
        try {
            if (! file_exists($audioAbsolutePath)) {
                return null;
            }

            $audioData = base64_encode(file_get_contents($audioAbsolutePath));
            $model = 'gemini-2.0-flash';

            $prompt = "Transcribe this audio file. Return ONLY a JSON object with keys: 
- language (e.g. 'id')
- text (the full transcript text)
- segments (an array of segments, each segment is an object with keys: start (float seconds), end (float seconds), and text (string)).

Ensure every segment has precise start and end times matching the audio. No markdown formatting, return ONLY the raw JSON.";

            $response = Http::timeout((int) config('cliptrend.openai.timeout', 900))
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => 'audio/wav',
                                        'data' => $audioData
                                    ]
                                ],
                                [
                                    'text' => $prompt
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json'
                    ]
                ]);

            if (! $response->successful()) {
                if ($response->status() === 429) {
                    throw new \RuntimeException("Gemini 429 Rate Limit Exceeded");
                }
                \Illuminate\Support\Facades\Log::warning('Gemini transcription failed', ['status' => $response->status(), 'body' => $response->body()]);
                return null;
            }

            $payload = $response->json();
            $text = $payload['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (! is_string($text) || trim($text) === '') {
                return null;
            }

            $json = json_decode($text, true);
            if (! is_array($json)) {
                return null;
            }

            return $this->normalizeTranscriptPayload($json, 'gemini');
        } catch (\Throwable $e) {
            if ($e->getMessage() === "Gemini 429 Rate Limit Exceeded") {
                throw $e;
            }
            \Illuminate\Support\Facades\Log::warning('Gemini transcription exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function transcribeWithLocalWhisper(string $audioAbsolutePath): ?array
    {
        $bin = config('cliptrend.whisper.bin') ?: env('WHISPER_BIN');
        if (! $bin || (! str_contains($bin, DIRECTORY_SEPARATOR) && trim((string) shell_exec('command -v '.escapeshellarg($bin))) === '') || (str_contains($bin, DIRECTORY_SEPARATOR) && ! is_executable($bin))) {
            return null;
        }

        $outputDir = dirname($audioAbsolutePath).'/whisper';
        @mkdir($outputDir, 0775, true);

        $language = config('cliptrend.whisper.language');
        $process = new Process(array_filter([
            $bin,
            $audioAbsolutePath,
            '--model', config('cliptrend.whisper.model', 'base'),
            $language ? '--language' : null,
            $language ?: null,
            '--output_format', 'json',
            '--output_dir', $outputDir,
            '--fp16', config('cliptrend.whisper.fp16', false) ? 'True' : 'False',
        ]));
        $process->setTimeout((int) config('cliptrend.analysis_timeout', 1800));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Local Whisper transcription failed: '.($process->getErrorOutput() ?: $process->getOutput()));
        }

        $jsonPath = $outputDir.'/'.pathinfo($audioAbsolutePath, PATHINFO_FILENAME).'.json';
        if (! file_exists($jsonPath)) {
            $matches = glob($outputDir.'/*.json') ?: [];
            $jsonPath = $matches[0] ?? null;
        }

        if (! $jsonPath || ! file_exists($jsonPath)) {
            throw new \RuntimeException('Local Whisper completed, but no JSON transcript was produced.');
        }

        return $this->normalizeTranscriptPayload(json_decode(file_get_contents($jsonPath), true) ?: [], 'local_whisper');
    }

    private function normalizeTranscriptPayload(array $payload, string $provider): array
    {
        $segments = collect($payload['segments'] ?? [])
            ->map(fn ($segment) => [
                'start' => round((float) ($segment['start'] ?? 0), 2),
                'end' => round((float) ($segment['end'] ?? 0), 2),
                'text' => trim((string) ($segment['text'] ?? '')),
            ])
            ->filter(fn ($segment) => $segment['end'] > $segment['start'] && $segment['text'] !== '')
            ->values()
            ->all();

        return [
            'language' => $payload['language'] ?? config('cliptrend.transcription_language', 'id'),
            'text' => trim((string) ($payload['text'] ?? collect($segments)->pluck('text')->implode(' '))),
            'segments' => $segments,
            'provider' => $provider,
            'raw_payload' => Arr::except($payload, ['segments']),
        ];
    }

    private function splitSubtitleSegment(array $segment, Clip $clip): array
    {
        $start = max(0, round(((float) $segment['start']) - $clip->start_time, 2));
        $end = min($clip->duration_seconds, round(((float) $segment['end']) - $clip->start_time, 2));
        $text = trim((string) ($segment['text'] ?? ''));

        if ($end <= $start || $text === '') {
            return [];
        }

        $words = preg_split('/\s+/', $text) ?: [];
        $groups = [];
        $current = [];
        foreach ($words as $word) {
            $current[] = $word;
            if (mb_strlen(implode(' ', $current)) >= 34 || count($current) >= 6) {
                $groups[] = implode(' ', $current);
                $current = [];
            }
        }
        if ($current) {
            $groups[] = implode(' ', $current);
        }

        $slice = max(0.35, ($end - $start) / max(1, count($groups)));
        return collect($groups)->map(fn ($group, $i) => [
            'start' => round($start + ($i * $slice), 2),
            'end' => round(min($end, $start + (($i + 1) * $slice)), 2),
            'text' => Str::upper($group),
        ])->all();
    }
}
