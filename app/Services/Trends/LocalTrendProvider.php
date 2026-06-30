<?php

namespace App\Services\Trends;

use App\Services\Contracts\TrendProviderInterface;
use Illuminate\Support\Str;

class LocalTrendProvider implements TrendProviderInterface
{
    public function analyze(array $filters): array
    {
        $topic = trim((string) (($filters['topic'] ?? '') ?: ($filters['niche'] ?? '')));
        $region = strtoupper((string) ($filters['region'] ?? 'ID'));
        $platform = strtolower((string) ($filters['platform'] ?? 'tiktok'));
        $seed = $topic !== '' ? $topic : 'konten short-form';
        
        $keywords = collect(preg_split('/\s+/', Str::of($seed)->lower()->ascii()->replaceMatches('/[^a-z0-9\s]/', ' ')->trim()) ?: [])
            ->filter(fn ($word) => strlen($word) >= 3)
            ->take(6)
            ->values()
            ->all();

        if ($keywords === []) {
            $keywords = ['motivasi', 'edukasi', 'tips', 'viral'];
        }

        // Try to load dynamic weekly trends database
        $trendsData = $this->loadTrendsJson();
        if ($trendsData) {
            return $this->analyzeWithTrendsJson($trendsData, $seed, $keywords, $platform, $region);
        }

        // Fallback to local heuristic if trends.json is missing or corrupted
        $hashtags = collect($keywords)
            ->map(fn ($keyword) => '#'.Str::of($keyword)->replaceMatches('/[^a-z0-9]/', '')->lower())
            ->filter(fn ($tag) => strlen($tag) > 1)
            ->prepend('#shortsindonesia')
            ->prepend('#fyp')
            ->unique()
            ->take(10)
            ->values()
            ->all();

        $score = round(min(88, 58 + (count($keywords) * 4) + ($topic !== '' ? 6 : 0)), 2);

        return [
            'score' => $score,
            'trends' => [
                ['label' => "Angle {$seed} untuk {$platform}", 'momentum' => 'high', 'fit' => (int) $score],
                ['label' => "Hook cepat seputar {$keywords[0]}", 'momentum' => 'rising', 'fit' => max(55, (int) $score - 6)],
                ['label' => "Format edukasi {$region} friendly", 'momentum' => 'steady', 'fit' => max(50, (int) $score - 10)],
            ],
            'hashtags' => $hashtags,
            'angles' => $this->angles($seed, $keywords),
            'hooks' => $this->hooks($seed, $keywords),
            'captions' => $this->captions($seed, $keywords),
            'raw_payload' => [
                'provider' => 'local_heuristic_fallback',
                'platform_target' => $platform,
                'region' => $region,
                'topic' => $topic,
                'notice' => 'Local fallback heuristics used.',
            ],
        ];
    }

    private function loadTrendsJson(): ?array
    {
        $path = storage_path('app/trends.json');
        if (! file_exists($path)) {
            return null;
        }
        $content = file_get_contents($path);
        if (! $content) {
            return null;
        }
        $json = json_decode($content, true);
        return is_array($json) ? $json : null;
    }

    private function analyzeWithTrendsJson(array $data, string $seed, array $keywords, string $platform, string $region): array
    {
        // Find best matching niche in trends database
        $bestNiche = null;
        $maxMatches = -1;
        
        $searchableText = strtolower($seed . ' ' . implode(' ', $keywords));

        foreach ($data['niches'] ?? [] as $nicheName => $nicheData) {
            $matches = 0;
            foreach ($nicheData['terms'] ?? [] as $term) {
                if (str_contains($searchableText, strtolower($term))) {
                    $matches++;
                }
            }
            if ($matches > $maxMatches) {
                $maxMatches = $matches;
                $bestNiche = array_merge($nicheData, ['name' => $nicheName]);
            }
        }

        // Default if no niche matches well
        if ($maxMatches <= 0 || ! $bestNiche) {
            $firstKey = array_key_first($data['niches'] ?? []);
            if ($firstKey) {
                $bestNiche = array_merge($data['niches'][$firstKey], ['name' => $firstKey]);
            }
        }

        $platformData = $data['platforms'][$platform] ?? ($data['platforms']['tiktok'] ?? []);
        
        $nicheHashtags = $bestNiche['hashtags'] ?? [];
        $platformHashtags = $platformData['trending_hashtags'] ?? [];
        $hashtags = collect(array_merge($nicheHashtags, $platformHashtags))
            ->unique()
            ->take(10)
            ->values()
            ->all();

        $score = round(min(98, 72 + ($maxMatches * 5) + (count($keywords) * 2)), 2);

        // Customize hooks and angles dynamically with seed/keyword replacements
        $hookSubject = $keywords[0] ?? $seed;
        $hooks = collect($platformData['hooks'] ?? [])->map(function ($hook) use ($hookSubject) {
            return str_replace(['ini', 'fakta tentang ini', 'tentang ini'], ["seputar {$hookSubject}", "fakta tentang {$hookSubject}", "tentang {$hookSubject}"], $hook);
        })->all();

        $angles = collect($platformData['angles'] ?? [])->map(function ($angle) use ($hookSubject) {
            return str_replace(['kontroversial:', 'micro-story:'], ["kontroversial seputar {$hookSubject}:", "micro-story {$hookSubject}:"], $angle);
        })->all();

        $captions = collect($platformData['captions'] ?? [])->map(function ($caption) use ($hookSubject) {
            return str_replace(['rahasianya', 'Ternyata'], ["rahasia {$hookSubject}", "Ternyata {$hookSubject}"], $caption);
        })->all();

        return [
            'score' => $score,
            'trends' => [
                ['label' => "Format {$bestNiche['name']} Angle untuk {$platform}", 'momentum' => 'high', 'fit' => (int) $score],
                ['label' => "Tren keyword {$hookSubject} sedang naik", 'momentum' => 'rising', 'fit' => max(60, (int) $score - 5)],
                ['label' => "Format visual: {$bestNiche['style']}", 'momentum' => 'steady', 'fit' => max(50, (int) $score - 12)],
            ],
            'hashtags' => $hashtags,
            'angles' => $angles ?: ["Angle: hubungkan topik {$hookSubject} dengan tren audiens {$region}."],
            'hooks' => $hooks ?: ["Hook: Rahasia sukses {$hookSubject} yang jarang dibahas."],
            'captions' => $captions ?: ["Caption: Pelajari {$hookSubject} ini secara detail sekarang."],
            'raw_payload' => [
                'provider' => 'weekly_trends_database',
                'niche_matched' => $bestNiche['name'] ?? 'none',
                'match_score' => $maxMatches,
                'platform_target' => $platform,
                'region' => $region,
                'last_updated' => $data['last_updated_at'] ?? 'unknown',
            ],
        ];
    }

    private function angles(string $topic, array $keywords): array
    {
        $keyword = $keywords[0] ?? $topic;
        return [
            "Angle cepat: hubungkan {$keyword} dengan masalah yang paling relate untuk audience Indonesia.",
            "Angle explain: bongkar alasan kenapa {$keyword} sedang ramai dibahas.",
            "Angle reaction: mulai dari satu pernyataan kuat lalu jawab dengan contoh nyata.",
        ];
    }

    private function hooks(string $topic, array $keywords): array
    {
        $keyword = $keywords[0] ?? $topic;
        return [
            "Banyak orang lihat {$keyword}, tapi melewatkan bagian ini...",
            "Kalau kamu mengikuti {$topic}, ini yang perlu kamu tahu dulu.",
            "Ini alasan {$keyword} bisa jadi konten yang gampang ditonton sampai habis.",
        ];
    }

    private function captions(string $topic, array $keywords): array
    {
        $keyword = $keywords[0] ?? $topic;
        return [
            "Ini yang bikin {$keyword} mulai ramai dibahas. Simpan dulu biar nggak ketinggalan.",
            "Bagian paling menarik dari {$topic} bukan cuma yang terlihat di awal.",
        ];
    }
}
