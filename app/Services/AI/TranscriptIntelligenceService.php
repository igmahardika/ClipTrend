<?php

namespace App\Services\AI;

use App\Models\UploadedVideo;
use App\Models\VideoAnalysis;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TranscriptIntelligenceService
{
    public function classify(UploadedVideo $video, array $transcript): array
    {
        $llm = $this->classifyWithOpenAi($video, $transcript);
        if ($llm) {
            return $this->normalizeClassification($llm, $video, $transcript, 'openai_responses_real_transcript');
        }

        return $this->ruleBasedClassification($video, $transcript, 'real_transcript_rule_engine_fallback');
    }

    public function detectClipCandidates(VideoAnalysis $analysis): array
    {
        $analysis->loadMissing(['uploadedVideo', 'project']);
        $transcript = $analysis->raw_payload['transcript'] ?? [];
        $segments = collect($transcript['segments'] ?? [])
            ->filter(fn ($s) => isset($s['start'], $s['end']) && trim($s['text'] ?? '') !== '')
            ->values();

        if ($segments->isEmpty()) {
            return $this->visualOnlyClipCandidates($analysis);
        }

        $windows = $this->buildTranscriptWindows($segments, $analysis);
        if (! $windows) {
            return $this->visualOnlyClipCandidates($analysis);
        }

        $llmRanked = $this->rankClipWindowsWithOpenAi($analysis, $windows);
        if ($llmRanked) {
            return $this->normalizeClipCandidates($llmRanked, $windows, $analysis, 'openai_ranked_real_transcript');
        }

        $selected = collect($windows)
            ->sortByDesc('score')
            ->reduce(function (array $carry, array $window) {
                foreach ($carry as $existing) {
                    if ($this->overlapRatio($window['start'], $window['end'], $existing['start'], $existing['end']) > 0.35) {
                        return $carry;
                    }
                }
                $carry[] = $window;
                return $carry;
            }, []);

        return collect($selected)
            ->take((int) config('cliptrend.max_candidate_clips', 5))
            ->values()
            ->map(fn ($window, $index) => $this->windowToClip($window, $analysis, $index + 1, 'real_transcript_scored_window'))
            ->all();
    }

    public function generateCopyPack(array $classification, array $transcript): array
    {
        $llm = $this->copyPackWithOpenAi($classification, $transcript);
        if ($llm) {
            return [
                'title' => Str::limit((string) ($llm['title'] ?? ''), 95, ''),
                'caption' => Str::limit((string) ($llm['caption'] ?? ''), 2200, ''),
                'hashtags' => $this->normalizeHashtags($llm['hashtags'] ?? []),
                'keywords' => array_values(array_slice($llm['keywords'] ?? ($classification['keywords'] ?? []), 0, 16)),
                'platform' => $llm['platform'] ?? ($classification['recommended_platforms'][0] ?? 'tiktok'),
                'source' => 'openai_generated_from_real_transcript',
            ];
        }

        $keywords = $classification['keywords'] ?? $this->extractKeywords($transcript['text'] ?? '');
        $topic = $classification['main_topic'] ?? $this->mainTopic($keywords, 'Konten Pendek');
        $niche = $classification['niches'][0]['name'] ?? 'Short-form Content';
        $hook = $this->hookFromText($transcript['text'] ?? $topic);
        $hashtags = collect(array_merge(
            $this->hashtagsForNiche($niche),
            collect($keywords)->take(5)->map(fn ($keyword) => '#'.Str::of($keyword)->replaceMatches('/[^a-z0-9]/i', '')->lower())->all(),
            ['#shortsindonesia', '#fyp']
        ))->filter()->unique()->take(12)->values()->all();

        return [
            'title' => Str::limit($this->titleFromText($transcript['text'] ?? $topic, $topic), 95, ''),
            'caption' => Str::limit($hook.' — '.($classification['summary'] ?? 'Potongan terbaik dari video ini siap dipakai untuk short-form content.'), 2200),
            'hashtags' => $hashtags,
            'keywords' => array_slice($keywords, 0, 12),
            'platform' => $classification['recommended_platforms'][0] ?? 'tiktok',
            'source' => 'real_transcript_rule_engine_fallback',
        ];
    }

    private function classifyWithOpenAi(UploadedVideo $video, array $transcript): ?array
    {
        $apiKey = config('cliptrend.openai.api_key') ?: env('OPENAI_API_KEY');
        $model = config('cliptrend.openai.text_model') ?: env('OPENAI_TEXT_MODEL');
        $text = trim((string) ($transcript['text'] ?? ''));
        if (! $apiKey || ! $model || $text === '') {
            return null;
        }

        $sample = Str::limit($text, (int) config('cliptrend.openai.max_analysis_chars', 16000), '');
        $prompt = [
            'role' => 'user',
            'content' => [[
                'type' => 'input_text',
                'text' => "Analyze this real video transcript for short-form repurposing. Return ONLY valid JSON with keys: summary, main_topic, content_style, audience {primary,intent,language}, niches array of {name,confidence_score,reason,signals,is_primary}, recommended_platforms, recommended_durations {shorts,tiktok,reels}, confidence, viral_score, reasoning, keywords. Use Indonesian language for user-facing strings. Transcript:\n\n".$sample,
            ]],
        ];

        return $this->openAiJson($model, [$prompt], 'classification');
    }

    private function rankClipWindowsWithOpenAi(VideoAnalysis $analysis, array $windows): ?array
    {
        $apiKey = config('cliptrend.openai.api_key') ?: env('OPENAI_API_KEY');
        $model = config('cliptrend.openai.text_model') ?: env('OPENAI_TEXT_MODEL');
        if (! $apiKey || ! $model || ! $windows) {
            return null;
        }

        $candidates = collect($windows)->sortByDesc('score')->take(15)->values()->map(fn ($w, $i) => [
            'id' => $i + 1,
            'start' => $w['start'],
            'end' => $w['end'],
            'duration' => $w['duration'],
            'text' => Str::limit($w['text'], 900, ''),
            'rule_score' => $w['score'],
        ])->all();

        $prompt = [
            'role' => 'user',
            'content' => [[
                'type' => 'input_text',
                'text' => 'Rank these transcript windows for TikTok/Reels/YouTube Shorts. Return ONLY JSON array of 3-5 items with keys: candidate_id, title, hook_text, reason, viral_score, retention_score, recommended_platforms. Pick only windows that can stand alone and have a strong opening. Main topic: '.($analysis->main_topic ?: 'unknown')."\n\nCandidates:\n".json_encode($candidates, JSON_UNESCAPED_UNICODE),
            ]],
        ];

        return $this->openAiJson($model, [$prompt], 'clip_ranking');
    }

    private function copyPackWithOpenAi(array $classification, array $transcript): ?array
    {
        $apiKey = config('cliptrend.openai.api_key') ?: env('OPENAI_API_KEY');
        $model = config('cliptrend.openai.text_model') ?: env('OPENAI_TEXT_MODEL');
        $text = trim((string) ($transcript['text'] ?? ''));
        if (! $apiKey || ! $model || $text === '') {
            return null;
        }

        $prompt = [
            'role' => 'user',
            'content' => [[
                'type' => 'input_text',
                'text' => 'Create upload-ready Indonesian copy for a short-form video based on this real transcript analysis. Return ONLY valid JSON: title max 95 chars, caption, hashtags array max 12, keywords array max 16, platform. Classification: '.json_encode(Arr::except($classification, ['raw']), JSON_UNESCAPED_UNICODE).' Transcript sample: '.Str::limit($text, 8000, ''),
            ]],
        ];

        return $this->openAiJson($model, [$prompt], 'copy_pack');
    }

    private function openAiJson(string $model, array $input, string $task): ?array
    {
        $apiKey = config('cliptrend.openai.api_key') ?: env('OPENAI_API_KEY');
        if (! $apiKey) {
            return null;
        }

        if (str_starts_with($apiKey, 'AIzaSy') || str_starts_with($apiKey, 'AQ.')) {
            $textPrompt = '';
            foreach ($input as $msg) {
                if (isset($msg['content'])) {
                    if (is_array($msg['content'])) {
                        foreach ($msg['content'] as $contentPart) {
                            if (is_array($contentPart) && isset($contentPart['text'])) {
                                $textPrompt .= $contentPart['text'] . "\n";
                            } elseif (is_string($contentPart)) {
                                $textPrompt .= $contentPart . "\n";
                            }
                        }
                    } elseif (is_string($msg['content'])) {
                        $textPrompt .= $msg['content'] . "\n";
                    }
                }
            }
            if (trim($textPrompt) === '') {
                $textPrompt = json_encode($input);
            }
            return $this->geminiJson($apiKey, $model, $textPrompt, $task);
        }

        try {
            $response = Http::timeout((int) config('cliptrend.openai.timeout', 900))
                ->withToken($apiKey)
                ->acceptJson()
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $model,
                    'input' => $input,
                    'temperature' => 0.2,
                    'text' => ['format' => ['type' => 'json_object']],
                    'store' => false,
                ]);

            if (! $response->successful()) {
                Log::warning('OpenAI JSON intelligence failed', ['task' => $task, 'status' => $response->status(), 'body' => $response->body()]);
                return null;
            }

            $payload = $response->json();
            $text = $payload['output_text'] ?? null;
            if (! $text && isset($payload['output'][0]['content'][0]['text'])) {
                $text = $payload['output'][0]['content'][0]['text'];
            }
            if (! is_string($text) || trim($text) === '') {
                return null;
            }

            $json = json_decode($text, true);
            return is_array($json) ? $json : null;
        } catch (\Throwable $e) {
            Log::warning('OpenAI JSON intelligence exception', ['task' => $task, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function geminiJson(string $apiKey, string $model, string $prompt, string $task): ?array
    {
        try {
            $model = str_starts_with($model, 'gemini-') ? $model : 'gemini-2.0-flash';
            
            $response = Http::timeout((int) config('cliptrend.openai.timeout', 900))
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature' => 0.2
                    ]
                ]);

            if (! $response->successful()) {
                Log::warning('Gemini JSON intelligence failed', ['task' => $task, 'status' => $response->status(), 'body' => $response->body()]);
                return null;
            }

            $payload = $response->json();
            $text = $payload['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (! is_string($text) || trim($text) === '') {
                return null;
            }

            $json = json_decode($text, true);
            return is_array($json) ? $json : null;
        } catch (\Throwable $e) {
            Log::warning('Gemini JSON intelligence exception', ['task' => $task, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function normalizeClassification(array $payload, UploadedVideo $video, array $transcript, string $source): array
    {
        $fallback = $this->ruleBasedClassification($video, $transcript, 'fallback_for_missing_llm_fields');
        $niches = collect($payload['niches'] ?? $fallback['niches'])->map(function ($niche, $index) use ($fallback) {
            return [
                'name' => (string) ($niche['name'] ?? ($fallback['niches'][$index]['name'] ?? 'General Content')),
                'confidence_score' => (float) ($niche['confidence_score'] ?? ($fallback['niches'][$index]['confidence_score'] ?? 50)),
                'reason' => (string) ($niche['reason'] ?? ($fallback['niches'][$index]['reason'] ?? 'Detected from transcript.')),
                'signals' => array_values(array_slice((array) ($niche['signals'] ?? []), 0, 10)),
                'is_primary' => $index === 0 || (bool) ($niche['is_primary'] ?? false),
            ];
        })->take(3)->values()->all();

        return [
            'summary' => (string) ($payload['summary'] ?? $fallback['summary']),
            'main_topic' => (string) ($payload['main_topic'] ?? $fallback['main_topic']),
            'content_style' => (string) ($payload['content_style'] ?? $fallback['content_style']),
            'audience' => (array) ($payload['audience'] ?? $fallback['audience']),
            'niches' => $niches,
            'recommended_platforms' => array_values((array) ($payload['recommended_platforms'] ?? $fallback['recommended_platforms'])),
            'recommended_durations' => (array) ($payload['recommended_durations'] ?? $fallback['recommended_durations']),
            'confidence' => (float) ($payload['confidence'] ?? ($niches[0]['confidence_score'] ?? $fallback['confidence'])),
            'viral_score' => (float) ($payload['viral_score'] ?? $fallback['viral_score']),
            'reasoning' => (string) ($payload['reasoning'] ?? 'Analisis dibuat dari transcript nyata dan metadata video.'),
            'keywords' => array_values(array_slice((array) ($payload['keywords'] ?? $fallback['keywords']), 0, 20)),
            'source' => $source,
        ];
    }

    private function ruleBasedClassification(UploadedVideo $video, array $transcript, string $source): array
    {
        $text = $this->normalizeText(trim(($transcript['text'] ?? '').' '.$video->original_filename.' '.$video->project?->title));
        $keywords = $this->extractKeywords($text);
        $duration = (float) ($video->duration_seconds ?? 0);
        $sceneChanges = count($transcript['signals']['scene_changes'] ?? []);
        $speechSegments = count($transcript['signals']['speech_segments'] ?? []);

        $scored = [];
        foreach ($this->nicheDictionary() as $niche => $data) {
            $score = 0;
            $signals = [];
            foreach ($data['terms'] as $term => $weight) {
                $count = substr_count($text, $this->normalizeText($term));
                if ($count > 0) {
                    $score += $count * $weight;
                    $signals[] = $term;
                }
            }
            if (($data['visual_hint'] ?? null) === 'many_scenes' && $sceneChanges >= 8) {
                $score += 4;
                $signals[] = 'banyak perubahan scene';
            }
            if (($data['visual_hint'] ?? null) === 'talking_head' && $speechSegments > 0 && $speechSegments <= max(8, $duration / 60)) {
                $score += 2;
                $signals[] = 'audio dominan/talking-head';
            }
            $scored[] = array_merge($data, ['name' => $niche, 'score' => $score, 'signals' => array_values(array_unique($signals))]);
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $topScore = max(1, (float) ($scored[0]['score'] ?? 1));
        $candidates = collect($scored)->take(3)->map(function ($item, $index) use ($topScore, $keywords, $transcript) {
            $confidence = $item['score'] > 0
                ? min(92, max(50, 54 + (($item['score'] / $topScore) * 34) - ($index * 8)))
                : max(26, 42 - ($index * 7));
            return [
                'name' => $item['name'],
                'confidence_score' => round($confidence, 2),
                'signals' => array_slice($item['signals'] ?: $keywords, 0, 8),
                'reason' => $item['score'] > 0
                    ? 'Dipilih dari transcript/metadata nyata: '.implode(', ', array_slice($item['signals'], 0, 6)).'.'
                    : 'Sinyal transcript rendah; klasifikasi berbasis metadata nyata dan pola audio/scene.',
                'is_primary' => $index === 0,
            ];
        })->values()->all();

        $primary = $scored[0] ?? null;
        $mainTopic = $this->mainTopic($keywords, $primary['name'] ?? 'General Content');
        $isVisualOnly = trim((string) ($transcript['text'] ?? '')) === '';

        return [
            'summary' => $isVisualOnly ? 'Video tidak memiliki transcript audio. Sistem membuat analisis visual-only dari metadata, scene changes, dan nama project.' : $this->summaryFromTranscript($transcript['text'] ?? '', $mainTopic),
            'main_topic' => $mainTopic,
            'content_style' => $primary['style'] ?? 'general short-form',
            'audience' => $primary['audience'] ?? ['primary' => 'Penonton short-form umum', 'intent' => 'mencari informasi cepat', 'language' => 'Indonesia'],
            'niches' => $candidates,
            'recommended_platforms' => $primary['platforms'] ?? ['shorts', 'tiktok', 'reels'],
            'recommended_durations' => $primary['durations'] ?? ['shorts' => 45, 'tiktok' => 35, 'reels' => 30],
            'confidence' => $candidates[0]['confidence_score'] ?? 0,
            'viral_score' => $this->estimateViralScore($transcript, $keywords, (float) ($primary['score'] ?? 0)),
            'reasoning' => 'V7 memakai data nyata: transcript timestamp jika ada, metadata ffprobe, audio/silence signal, dan scene-change signal. Tidak memakai dummy transcript.',
            'keywords' => $keywords,
            'source' => $source,
        ];
    }

    private function buildTranscriptWindows($segments, VideoAnalysis $analysis): array
    {
        $windows = [];
        $minDuration = (int) config('cliptrend.min_clip_seconds', 18);
        $maxDuration = (int) config('cliptrend.max_clip_seconds', 75);
        for ($i = 0; $i < $segments->count(); $i++) {
            $start = (float) $segments[$i]['start'];
            $text = '';
            $end = $start;
            $segmentCount = 0;
            for ($j = $i; $j < $segments->count(); $j++) {
                $end = (float) $segments[$j]['end'];
                $duration = $end - $start;
                if ($duration > $maxDuration) {
                    break;
                }
                $text = trim($text.' '.($segments[$j]['text'] ?? ''));
                $segmentCount++;
                if ($duration >= $minDuration) {
                    $windows[] = [
                        'start' => $start,
                        'end' => $end,
                        'duration' => $duration,
                        'text' => $text,
                        'score' => $this->scoreClipWindow($text, $duration, $segmentCount, $start, $analysis),
                        'segment_count' => $segmentCount,
                    ];
                }
            }
        }
        return $windows;
    }

    private function visualOnlyClipCandidates(VideoAnalysis $analysis): array
    {
        $duration = max(1, (float) ($analysis->uploadedVideo?->duration_seconds ?? 60));
        $target = min(45, max(12, $duration >= 45 ? 35 : $duration));
        $sceneTimes = collect($analysis->raw_payload['transcript']['signals']['scene_changes'] ?? [])->filter(fn ($t) => is_numeric($t))->values();
        $starts = $sceneTimes->filter(fn ($t) => $t > 2 && $t < max(3, $duration - $target))->take(5)->values();
        if ($starts->isEmpty()) {
            $starts = collect([0, max(0, $duration * 0.25), max(0, $duration * 0.5)])->unique()->values();
        }

        return $starts->take((int) config('cliptrend.max_candidate_clips', 5))->map(function ($start, $index) use ($target, $duration, $analysis) {
            $end = min($duration, (float) $start + $target);
            return [
                'title' => Str::headline(Str::limit($analysis->main_topic ?: $analysis->project?->title ?: 'Visual Clip', 80, '')),
                'description' => 'Clip visual-only dari video nyata. Tidak ada transcript audio yang tersedia.',
                'start_seconds' => round((float) $start, 2),
                'end_seconds' => round($end, 2),
                'duration_seconds' => round(max(1, $end - (float) $start), 2),
                'hook_text' => Str::limit($analysis->main_topic ?: 'Visual highlight siap upload', 115, ''),
                'retention_score' => 55 - ($index * 4),
                'viral_score' => 52 - ($index * 4),
                'recommended_platforms' => ['shorts' => 60, 'tiktok' => 60, 'reels' => 60],
                'source' => 'real_video_visual_only',
                'rank' => $index + 1,
            ];
        })->all();
    }

    private function normalizeClipCandidates(array $llmRanked, array $windows, VideoAnalysis $analysis, string $source): array
    {
        $byId = collect($windows)->sortByDesc('score')->take(15)->values()->keyBy(fn ($w, $i) => $i + 1);
        return collect($llmRanked)->map(function ($item, $index) use ($byId, $analysis, $source) {
            $window = $byId[(int) ($item['candidate_id'] ?? 0)] ?? null;
            if (! $window) {
                return null;
            }
            return array_merge($this->windowToClip($window, $analysis, $index + 1, $source), [
                'title' => Str::limit((string) ($item['title'] ?? ''), 95, '') ?: $this->titleFromText($window['text'], $analysis->main_topic ?: 'Clip'),
                'hook_text' => Str::limit((string) ($item['hook_text'] ?? ''), 130, '') ?: $this->hookFromText($window['text']),
                'description' => (string) ($item['reason'] ?? Str::limit($window['text'], 320)),
                'viral_score' => (float) ($item['viral_score'] ?? $window['score']),
                'retention_score' => (float) ($item['retention_score'] ?? $window['score'] - 3),
                'recommended_platforms' => (array) ($item['recommended_platforms'] ?? $this->platformFit((float) $window['score'], (float) $window['duration'])),
            ]);
        })->filter()->take((int) config('cliptrend.max_candidate_clips', 5))->values()->all();
    }

    private function windowToClip(array $window, VideoAnalysis $analysis, int $rank, string $source): array
    {
        $hook = $this->hookFromText($window['text']);
        $title = $this->titleFromText($window['text'], $analysis->main_topic ?: 'Clip');
        $viral = min(98, max(45, round((float) $window['score'], 2)));
        $retention = min(96, max(45, round((float) $window['score'] - 4 + min(8, (int) ($window['segment_count'] ?? 1)), 2)));
        return [
            'title' => $title,
            'description' => Str::limit($window['text'], 360),
            'start_seconds' => round(max(0, (float) $window['start'] - 0.25), 2),
            'end_seconds' => round((float) $window['end'] + 0.25, 2),
            'duration_seconds' => round(((float) $window['end'] + 0.25) - max(0, (float) $window['start'] - 0.25), 2),
            'hook_text' => $hook,
            'retention_score' => $retention,
            'viral_score' => $viral,
            'recommended_platforms' => $this->platformFit($viral, (float) $window['duration']),
            'source' => $source,
            'rank' => $rank,
        ];
    }

    private function normalizeText(string $text): string
    {
        return Str::of($text)->lower()->ascii()->replaceMatches('/[^a-z0-9\s]/', ' ')->replaceMatches('/\s+/', ' ')->trim()->toString();
    }

    private function extractKeywords(string $text): array
    {
        $stop = array_flip(['yang','dan','di','ke','dari','ini','itu','untuk','dengan','kita','saya','aku','kamu','anda','ada','jadi','karena','dalam','adalah','atau','kalau','akan','bisa','tidak','nggak','gak','nya','sih','dong','the','a','of','to','is','are','on','in','for','was','were']);
        $words = preg_split('/\s+/', $this->normalizeText($text)) ?: [];
        $counts = [];
        foreach ($words as $word) {
            if (strlen($word) < 4 || isset($stop[$word]) || is_numeric($word)) {
                continue;
            }
            $counts[$word] = ($counts[$word] ?? 0) + 1;
        }
        arsort($counts);
        return array_slice(array_keys($counts), 0, 20);
    }

    private function nicheDictionary(): array
    {
        return [
            'Podcast Motivasi' => ['style' => 'motivasi / podcast talking-head', 'visual_hint' => 'talking_head', 'audience' => ['primary' => 'usia 18-35, pekerja muda, mahasiswa, pencari motivasi', 'intent' => 'mencari kalimat relatable dan insight hidup', 'language' => 'Indonesia'], 'durations' => ['shorts' => 45, 'tiktok' => 38, 'reels' => 35], 'platforms' => ['tiktok', 'shorts', 'reels'], 'terms' => ['hidup'=>4,'tanggung jawab'=>5,'dewasa'=>5,'motivasi'=>6,'kuat'=>3,'mental'=>4,'sukses'=>4,'mimpi'=>4,'gagal'=>4,'semangat'=>5,'perjuangan'=>4,'takut'=>3,'berani'=>4]],
            'Edukasi Bisnis' => ['style' => 'edukasi / business insight', 'audience' => ['primary' => 'founder, sales, marketer, pekerja profesional', 'intent' => 'mencari strategi praktis dan insight bisnis', 'language' => 'Indonesia'], 'durations' => ['shorts' => 50, 'tiktok' => 45, 'reels' => 40], 'platforms' => ['shorts', 'tiktok', 'reels'], 'terms' => ['bisnis'=>6,'jualan'=>5,'marketing'=>6,'customer'=>5,'produk'=>4,'brand'=>4,'omset'=>5,'sales'=>5,'strategi'=>4,'profit'=>4,'market'=>3,'usaha'=>5,'target'=>3,'closing'=>4]],
            'Tutorial' => ['style' => 'tutorial / step-by-step', 'audience' => ['primary' => 'user yang mencari panduan praktis', 'intent' => 'belajar langkah cepat', 'language' => 'Indonesia'], 'durations' => ['shorts' => 60, 'tiktok' => 55, 'reels' => 50], 'platforms' => ['shorts', 'reels', 'tiktok'], 'terms' => ['cara'=>5,'tutorial'=>6,'langkah'=>5,'setup'=>4,'install'=>4,'gunakan'=>3,'tips'=>4,'trik'=>4,'panduan'=>5,'setting'=>4]],
            'Event / Konser' => ['style' => 'event hype / entertainment', 'visual_hint' => 'many_scenes', 'audience' => ['primary' => 'penonton musik, event-goers, komunitas lokal', 'intent' => 'mencari info acara dan highlight atmosfer', 'language' => 'Indonesia'], 'durations' => ['shorts' => 30, 'tiktok' => 22, 'reels' => 25], 'platforms' => ['tiktok', 'reels', 'shorts'], 'terms' => ['konser'=>6,'musik'=>4,'panggung'=>5,'semarang'=>3,'tiket'=>5,'lineup'=>5,'artist'=>4,'penonton'=>4,'event'=>6,'festival'=>5,'manggung'=>5,'lagu'=>3]],
            'Sepak Bola' => ['style' => 'sports editorial / football news', 'audience' => ['primary' => 'fans sepak bola dan Timnas', 'intent' => 'mencari update, analisis, dan emosi pertandingan', 'language' => 'Indonesia'], 'durations' => ['shorts' => 40, 'tiktok' => 35, 'reels' => 30], 'platforms' => ['tiktok', 'shorts', 'reels'], 'terms' => ['bola'=>6,'timnas'=>6,'gol'=>5,'pemain'=>4,'pelatih'=>4,'liga'=>4,'pertandingan'=>5,'menang'=>4,'kalah'=>4,'ranking'=>3,'fifa'=>4,'stadion'=>3,'final'=>4]],
            'Berita / Commentary' => ['style' => 'news commentary / explain the impact', 'audience' => ['primary' => 'penonton yang mengikuti isu terbaru', 'intent' => 'mencari ringkasan cepat dan konteks', 'language' => 'Indonesia'], 'durations' => ['shorts' => 50, 'tiktok' => 45, 'reels' => 40], 'platforms' => ['shorts', 'tiktok', 'reels'], 'terms' => ['berita'=>6,'update'=>4,'viral'=>4,'pemerintah'=>4,'presiden'=>5,'kasus'=>4,'terbaru'=>4,'isu'=>4,'kabar'=>4,'fakta'=>4,'analisis'=>4]],
            'Review Produk' => ['style' => 'review / product recommendation', 'audience' => ['primary' => 'calon pembeli dan tech/product enthusiast', 'intent' => 'membandingkan produk sebelum membeli', 'language' => 'Indonesia'], 'durations' => ['shorts' => 45, 'tiktok' => 40, 'reels' => 35], 'platforms' => ['tiktok', 'reels', 'shorts'], 'terms' => ['review'=>6,'produk'=>5,'harga'=>4,'beli'=>4,'kamera'=>4,'fitur'=>5,'kualitas'=>4,'bagus'=>3,'worth'=>5,'rekomendasi'=>5,'unboxing'=>5]],
            'Customer Service Brand' => ['style' => 'brand support / service communication', 'audience' => ['primary' => 'pelanggan brand dan calon pelanggan', 'intent' => 'mencari solusi cepat atau trust signal', 'language' => 'Indonesia'], 'durations' => ['shorts' => 35, 'tiktok' => 30, 'reels' => 30], 'platforms' => ['reels', 'shorts', 'tiktok'], 'terms' => ['pelanggan'=>6,'layanan'=>5,'komplain'=>5,'internet'=>5,'gangguan'=>5,'helpdesk'=>5,'modem'=>4,'koneksi'=>5,'ticket'=>4,'normal'=>3,'customer'=>4]],
        ];
    }

    private function mainTopic(array $keywords, string $fallback): string
    {
        return collect($keywords)->take(4)->map(fn ($keyword) => Str::headline($keyword))->implode(' · ') ?: $fallback;
    }

    private function summaryFromTranscript(string $text, string $topic): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text));
        return $clean ? Str::limit('Video membahas '.$topic.'. Inti transcript: '.$clean, 420) : 'Video membahas '.$topic.' berdasarkan data nyata.';
    }

    private function estimateViralScore(array $transcript, array $keywords, float $nicheScore): float
    {
        $text = $this->normalizeText($transcript['text'] ?? '');
        $emotionalTerms = ['takut','gagal','berani','kuat','sukses','viral','penting','jangan','harus','masalah','rahasia','salah','benar','menang','kalah','ternyata'];
        $emotion = collect($emotionalTerms)->sum(fn ($term) => substr_count($text, $term));
        $segments = count($transcript['segments'] ?? []);
        $sceneChanges = count($transcript['signals']['scene_changes'] ?? []);
        return round(min(96, max(35, 42 + min(22, $emotion * 3) + min(14, $nicheScore) + min(10, $segments / 6) + min(6, $sceneChanges / 8) + min(8, count($keywords) / 2))), 2);
    }

    private function scoreClipWindow(string $text, float $duration, int $segmentCount, float $start, VideoAnalysis $analysis): float
    {
        $normalized = $this->normalizeText($text);
        $hookTerms = ['kenapa','jangan','ternyata','rahasia','masalah','penting','tapi','kalau','sebenarnya','banyak orang','harus','gagal','salah','benar','viral','ini yang'];
        $emotionalTerms = ['takut','berani','sedih','marah','kuat','lelah','tanggung jawab','mimpi','sukses','gagal','menang','kalah','percaya'];
        $score = 48;
        $score += collect($hookTerms)->sum(fn ($term) => str_contains($normalized, $this->normalizeText($term)) ? 4 : 0);
        $score += collect($emotionalTerms)->sum(fn ($term) => substr_count($normalized, $this->normalizeText($term)) * 2.4);
        $score += min(10, strlen($normalized) / 110);
        $score += $duration >= 25 && $duration <= 48 ? 9 : ($duration <= 75 ? 4 : 0);
        $score += min(6, $segmentCount / 2.5);
        $score += $start < 120 ? 3 : 0;
        $score += min(8, (float) ($analysis->ai_confidence ?? 0) / 14);
        return round($score, 2);
    }

    private function overlapRatio(float $aStart, float $aEnd, float $bStart, float $bEnd): float
    {
        $overlap = max(0, min($aEnd, $bEnd) - max($aStart, $bStart));
        $length = max(1, min($aEnd - $aStart, $bEnd - $bStart));
        return $overlap / $length;
    }

    private function hookFromText(string $text): string
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($text)) ?: [];
        $candidate = collect($sentences)->first(fn ($sentence) => mb_strlen(trim($sentence)) >= 25) ?: trim($text);
        return Str::limit($candidate, 115, '');
    }

    private function titleFromText(string $text, string $topic): string
    {
        $hook = preg_replace('/\s+/', ' ', $this->hookFromText($text));
        return Str::headline(Str::limit($hook ?: $topic, 80, ''));
    }

    private function platformFit(float $viral, float $duration): array
    {
        return [
            'shorts' => (int) min(98, $viral + ($duration <= 60 ? 6 : 0)),
            'tiktok' => (int) min(98, $viral + ($duration <= 45 ? 8 : 2)),
            'reels' => (int) min(98, $viral + ($duration <= 35 ? 7 : 1)),
        ];
    }

    private function hashtagsForNiche(string $niche): array
    {
        return match ($niche) {
            'Podcast Motivasi' => ['#motivasihidup', '#podcastindonesia', '#katakatabijak'],
            'Edukasi Bisnis' => ['#bisnisindonesia', '#marketingtips', '#edukasibisnis'],
            'Tutorial' => ['#tutorialindonesia', '#tipspraktis', '#belajarbareng'],
            'Event / Konser' => ['#infokonser', '#eventsemarang', '#konserindonesia'],
            'Sepak Bola' => ['#timnasindonesia', '#sepakbola', '#footballtiktok'],
            'Berita / Commentary' => ['#beritaterkini', '#faktaviral', '#explainer'],
            'Review Produk' => ['#reviewproduk', '#rekomendasi', '#unboxing'],
            'Customer Service Brand' => ['#customerservice', '#layananpelanggan', '#brandtrust'],
            default => ['#shorts', '#reelsindonesia', '#tiktokindonesia'],
        };
    }

    private function normalizeHashtags(array $hashtags): array
    {
        return collect($hashtags)
            ->map(fn ($tag) => '#'.ltrim((string) $tag, '#'))
            ->map(fn ($tag) => preg_replace('/\s+/', '', $tag))
            ->filter(fn ($tag) => strlen($tag) > 1)
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }
}
