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
        private readonly RealAiProvider $realAi,
    ) {}

    public function transcribe(UploadedVideo $video): array
    {
        return $this->realAi->transcribe($video);
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

        Log::warning('Ollama classification failed or returned empty. Falling back to RealAiProvider.');
        return $this->realAi->classifyVideo($video, $transcript);
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
        
        $multimodal = $analysis->raw_payload['transcript']['signals']['multimodal'] ?? [];
        $audioEnergy = $multimodal['audio']['audio_energy'] ?? [];
        $motionEnergy = $multimodal['video']['motion_energy'] ?? [];

        $candidates = collect($windows)->sortByDesc('score')->take(10)->values()->map(function($w, $i) use ($audioEnergy, $motionEnergy) {
            $startSec = intval($w['start']);
            $endSec = intval($w['end']);
            
            $ae = array_slice($audioEnergy, $startSec, max(1, $endSec - $startSec));
            $me = array_slice($motionEnergy, $startSec, max(1, $endSec - $startSec));
            
            $avgAe = count($ae) > 0 ? array_sum($ae) / count($ae) : 0;
            $avgMe = count($me) > 0 ? array_sum($me) / count($me) : 0;
            
            return [
                'id' => $i + 1,
                'start' => $w['start'],
                'end' => $w['end'],
                'duration' => $w['duration'],
                'text' => Str::limit($w['text'], 800, ''),
                'audio_energy_score' => round($avgAe, 1),
                'motion_energy_score' => round($avgMe, 1),
            ];
        })->all();

        $prompt = 'Rank these transcript windows for TikTok/Reels/YouTube Shorts. Return ONLY a JSON array of 3-5 items with keys: candidate_id, title, hook_text, reason, viral_score, retention_score, recommended_platforms. Pick only windows that can stand alone and have a strong opening. Consider the audio_energy_score (shouting/laughing/excitement) and motion_energy_score (action/gestures) when scoring viral_score! Main topic: '.($analysis->main_topic ?: 'unknown')."\n\nCandidates:\n".json_encode($candidates, JSON_UNESCAPED_UNICODE);

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

        Log::warning('Ollama clip candidates generation failed or returned empty. Falling back to RealAiProvider.');
        return $this->realAi->detectClipCandidates($analysis);
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

        Log::warning('Ollama copy pack generation failed. Falling back to RealAiProvider.');
        return $this->realAi->generateCopyPack($classification, $transcript);
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
