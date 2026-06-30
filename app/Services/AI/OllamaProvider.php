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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class OllamaProvider implements AiProviderInterface
{
    public function __construct(
        private readonly AudioExtractionService $audio,
        private readonly VideoSignalService $signals,
    ) {}

    public function transcribe(UploadedVideo $video): array
    {
        $audio = $this->audio->extractWav($video);
        $transcript = null;

        if ($audio) {
            $transcript = $this->transcribeWithLocalWhisper($audio['absolute_path']);
            if (! $transcript) {
                $transcript = [
                    'language' => config('cliptrend.transcription_language', 'id'),
                    'text' => '',
                    'segments' => [],
                    'provider' => 'visual_only_transcription_unavailable',
                    'raw_payload' => [
                        'notice' => 'Transcription skipped because WHISPER_BIN is not configured or failed. Ollama requires text transcript for best results.',
                    ],
                ];
            }
        } else {
            $transcript = [
                'language' => config('cliptrend.transcription_language', 'id'),
                'text' => '',
                'segments' => [],
                'provider' => 'visual_only_no_audio_stream',
                'raw_payload' => ['notice' => 'No audio stream detected.'],
            ];
        }

        $videoSignals = $this->signals->inspectSignals($video);

        return array_merge($transcript, [
            'provider' => $transcript['provider'] ?? 'ollama_local_whisper',
            'audio' => $audio ? Arr::except($audio, ['absolute_path']) : null,
            'signals' => $videoSignals,
            'source' => $audio ? 'ollama_uploaded_video_audio_transcript' : 'ollama_uploaded_video_visual_only',
        ]);
    }

    public function classifyVideo(UploadedVideo $video, array $transcript): array
    {
        $text = trim((string) ($transcript['text'] ?? ''));
        if ($text === '') {
            return $this->fallbackClassification();
        }

        $sample = Str::limit($text, 12000, '');
        $prompt = "Analyze this video transcript for short-form repurposing. Return ONLY valid JSON with keys: summary, main_topic, content_style, audience {primary,intent,language}, niches array of {name,confidence_score,reason,signals,is_primary}, recommended_platforms, recommended_durations {shorts,tiktok,reels}, confidence, viral_score, reasoning, keywords. Use Indonesian language. Transcript:\n\n" . $sample;

        $response = $this->ollamaGenerate($prompt);
        if ($response) {
            return $this->normalizeClassification($response);
        }

        return $this->fallbackClassification();
    }

    public function detectClipCandidates(VideoAnalysis $analysis): array
    {
        $transcript = $analysis->raw_payload['transcript'] ?? [];
        $segments = collect($transcript['segments'] ?? [])
            ->filter(fn ($s) => isset($s['start'], $s['end']) && trim($s['text'] ?? '') !== '')
            ->values();

        if ($segments->isEmpty()) {
            return [];
        }

        $windows = $this->buildTranscriptWindows($segments, $analysis);
        if (! $windows) {
            return [];
        }

        $candidates = collect($windows)->sortByDesc('score')->take(10)->values()->map(fn ($w, $i) => [
            'id' => $i + 1,
            'start' => $w['start'],
            'end' => $w['end'],
            'duration' => $w['duration'],
            'text' => Str::limit($w['text'], 800, ''),
        ])->all();

        $prompt = 'Rank these transcript windows for TikTok/Reels/YouTube Shorts. Return ONLY a JSON array of 3-5 items with keys: candidate_id, title, hook_text, reason, viral_score, retention_score, recommended_platforms. Pick only windows that can stand alone and have a strong opening. Main topic: '.($analysis->main_topic ?: 'unknown')."\n\nCandidates:\n".json_encode($candidates, JSON_UNESCAPED_UNICODE);

        $response = $this->ollamaGenerate($prompt);
        if (is_array($response) && count($response) > 0) {
            $clips = [];
            foreach ($response as $item) {
                $cid = $item['candidate_id'] ?? null;
                $window = collect($windows)->firstWhere('id', $cid) ?? $windows[0] ?? null;
                if ($window) {
                    $clips[] = [
                        'title' => $item['title'] ?? 'Clip',
                        'hook_text' => $item['hook_text'] ?? null,
                        'viral_score' => $item['viral_score'] ?? 80,
                        'retention_score' => $item['retention_score'] ?? 80,
                        'start_time' => $window['start'],
                        'end_time' => $window['end'],
                        'duration' => $window['duration'],
                        'transcript_excerpt' => Str::limit($window['text'], 200),
                        'confidence_score' => $item['viral_score'] ?? 80,
                        'source' => 'ollama_ranked',
                    ];
                }
            }
            return $clips;
        }

        return [];
    }

    public function generateCopyPack(array $classification, array $transcript): array
    {
        $prompt = "Create a viral copy pack for a short video based on this classification. Return ONLY valid JSON with keys: title, caption, hashtags (array of strings starting with #), keywords (array of strings), platform. Data:\n" . json_encode($classification);
        $response = $this->ollamaGenerate($prompt);

        if ($response) {
            return [
                'title' => Str::limit((string) ($response['title'] ?? 'Viral Video'), 95, ''),
                'caption' => Str::limit((string) ($response['caption'] ?? ''), 2200, ''),
                'hashtags' => $response['hashtags'] ?? [],
                'keywords' => $response['keywords'] ?? [],
                'platform' => $response['platform'] ?? 'tiktok',
                'source' => 'ollama_generated',
            ];
        }

        return [
            'title' => 'Video Keren',
            'caption' => 'Cek video ini! #viral',
            'hashtags' => ['#fyp', '#viral'],
            'keywords' => [],
            'platform' => 'tiktok',
            'source' => 'fallback',
        ];
    }

    public function generateSubtitleSegments(Clip $clip, array $transcriptSegments = []): array
    {
        return collect($transcriptSegments)
            ->filter(fn ($segment) => ($segment['end'] ?? 0) > $clip->start_time && ($segment['start'] ?? 0) < $clip->end_time)
            ->flatMap(fn ($segment) => $this->splitSubtitleSegment($segment, $clip))
            ->filter(fn ($segment) => trim($segment['text']) !== '' && $segment['end'] > $segment['start'])
            ->values()
            ->all();
    }

    private function ollamaGenerate(string $prompt): ?array
    {
        $model = config('cliptrend.ollama.model', 'llama3.2');
        $url = config('cliptrend.ollama.url', 'http://127.0.0.1:11434');

        try {
            $response = Http::timeout(300)->post("{$url}/api/generate", [
                'model' => $model,
                'prompt' => $prompt,
                'format' => 'json',
                'stream' => false,
            ]);

            if ($response->successful()) {
                $json = json_decode($response->json('response'), true);
                return is_array($json) ? $json : null;
            }
        } catch (\Throwable $e) {
            Log::error('Ollama Generation Failed', ['error' => $e->getMessage()]);
        }

        return null;
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
        $process->setTimeout(1800);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $jsonPath = $outputDir.'/'.pathinfo($audioAbsolutePath, PATHINFO_FILENAME).'.json';
        if (! file_exists($jsonPath)) {
            $matches = glob($outputDir.'/*.json') ?: [];
            $jsonPath = $matches[0] ?? null;
        }

        if (! $jsonPath || ! file_exists($jsonPath)) {
            return null;
        }

        $payload = json_decode(file_get_contents($jsonPath), true) ?: [];
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
            'language' => $payload['language'] ?? 'id',
            'text' => trim((string) ($payload['text'] ?? collect($segments)->pluck('text')->implode(' '))),
            'segments' => $segments,
            'provider' => 'ollama_local_whisper',
            'raw_payload' => Arr::except($payload, ['segments']),
        ];
    }

    private function normalizeClassification(array $classification): array
    {
        return [
            'summary' => $classification['summary'] ?? null,
            'main_topic' => $classification['main_topic'] ?? null,
            'audience' => $classification['audience'] ?? [],
            'content_style' => $classification['content_style'] ?? null,
            'niches' => $classification['niches'] ?? [
                ['name' => 'General', 'confidence_score' => 80, 'is_primary' => true],
            ],
            'recommended_platforms' => $classification['recommended_platforms'] ?? ['tiktok'],
            'recommended_durations' => $classification['recommended_durations'] ?? [],
            'confidence' => $classification['confidence'] ?? 80,
            'viral_score' => $classification['viral_score'] ?? 80,
            'reasoning' => $classification['reasoning'] ?? null,
            'keywords' => $classification['keywords'] ?? [],
            'source' => 'ollama',
        ];
    }

    private function fallbackClassification(): array
    {
        return $this->normalizeClassification([
            'summary' => 'Fallback analysis due to parsing error.',
            'niches' => [['name' => 'Uncategorized', 'confidence_score' => 50, 'is_primary' => true]],
        ]);
    }

    private function buildTranscriptWindows(iterable $segments, VideoAnalysis $analysis): array
    {
        $min = 18;
        $max = 75;
        $windows = [];
        $segments = collect($segments)->values()->all();

        for ($i = 0; $i < count($segments); $i++) {
            $start = $segments[$i]['start'];
            $text = [];
            $end = $start;

            for ($j = $i; $j < count($segments); $j++) {
                $end = $segments[$j]['end'];
                $text[] = $segments[$j]['text'];
                $duration = $end - $start;

                if ($duration >= $min && $duration <= $max) {
                    $windows[] = [
                        'id' => uniqid(),
                        'start' => $start,
                        'end' => $end,
                        'duration' => $duration,
                        'text' => implode(' ', $text),
                        'score' => 80, 
                    ];
                }

                if ($duration > $max) break;
            }
        }

        return $windows;
    }

    private function splitSubtitleSegment(array $segment, Clip $clip): array
    {
        $start = max(0, round(((float) $segment['start']) - $clip->start_time, 2));
        $end = max($start + 0.1, round(((float) $segment['end']) - $clip->start_time, 2));
        $words = explode(' ', trim($segment['text']));
        $chunks = array_chunk($words, 4);

        $duration = $end - $start;
        $chunkDuration = $duration / max(1, count($chunks));
        
        $result = [];
        $currentStart = $start;
        
        foreach ($chunks as $chunk) {
            $chunkEnd = $currentStart + $chunkDuration;
            $result[] = [
                'start' => round($currentStart, 2),
                'end' => round($chunkEnd, 2),
                'text' => implode(' ', $chunk),
            ];
            $currentStart = $chunkEnd;
        }

        return $result;
    }
}
