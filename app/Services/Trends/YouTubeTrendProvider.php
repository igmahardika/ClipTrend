<?php

namespace App\Services\Trends;

use App\Services\Contracts\TrendProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class YouTubeTrendProvider implements TrendProviderInterface
{
    public function analyze(array $filters): array
    {
        $apiKey = env('YOUTUBE_DATA_API_KEY');
        if (! $apiKey) {
            throw new \RuntimeException('Real Trend Checker requires YOUTUBE_DATA_API_KEY when TREND_PROVIDER=youtube. V7 does not return dummy trend data by default.');
        }

        $topic = trim(($filters['topic'] ?? '') ?: ($filters['niche'] ?? ''));
        $region = strtoupper($filters['region'] ?? 'ID');
        $platform = $filters['platform'] ?? 'tiktok';

        $items = $this->fetchTrendingVideos($apiKey, $region, $topic);
        $titles = collect($items)->map(fn ($item) => $item['snippet']['title'] ?? '')->filter()->values();
        $descriptions = collect($items)->map(fn ($item) => $item['snippet']['description'] ?? '')->filter()->values();
        $tags = collect($items)->flatMap(fn ($item) => $item['snippet']['tags'] ?? [])->map(fn ($tag) => Str::lower($tag))->filter()->countBy()->sortDesc();
        $titleTerms = $this->keywordCounts($titles->implode(' '));

        $keywords = collect(array_merge($tags->keys()->take(10)->all(), array_keys(array_slice($titleTerms, 0, 10, true))))
            ->map(fn ($keyword) => trim((string) $keyword))
            ->filter(fn ($keyword) => strlen($keyword) >= 3)
            ->unique()
            ->take(12)
            ->values()
            ->all();

        $hashtags = collect($keywords)
            ->map(fn ($keyword) => '#'.Str::of($keyword)->replaceMatches('/[^A-Za-z0-9]+/', '')->lower())
            ->filter(fn ($tag) => strlen($tag) > 1)
            ->unique()
            ->prepend('#shortsindonesia')
            ->prepend('#fyp')
            ->take(10)
            ->values()
            ->all();

        $topTitles = $titles->take(5)->all();
        $score = $this->score($items, $topic);

        return [
            'score' => $score,
            'trends' => collect($topTitles)->map(fn ($title, $i) => [
                'label' => $title,
                'momentum' => $i < 2 ? 'high' : 'rising',
                'fit' => max(60, (int) round($score - ($i * 4))),
            ])->all(),
            'hashtags' => $hashtags,
            'angles' => $this->angles($topic, $keywords, $topTitles),
            'hooks' => $this->hooks($topic, $keywords, $topTitles),
            'captions' => $this->captions($topic, $keywords),
            'raw_payload' => [
                'provider' => 'youtube_data_api',
                'platform_target' => $platform,
                'region' => $region,
                'topic' => $topic,
                'sample_size' => count($items),
                'top_titles' => $topTitles,
                'keyword_counts' => array_slice($titleTerms, 0, 20, true),
                'tag_counts' => $tags->take(20)->all(),
            ],
        ];
    }

    private function fetchTrendingVideos(string $apiKey, string $region, string $topic): array
    {
        if ($topic !== '') {
            $search = Http::timeout(20)->get('https://www.googleapis.com/youtube/v3/search', [
                'part' => 'snippet',
                'type' => 'video',
                'maxResults' => 15,
                'regionCode' => $region,
                'order' => 'viewCount',
                'q' => $topic,
                'key' => $apiKey,
            ]);

            if (! $search->successful()) {
                throw new \RuntimeException('YouTube trend search failed: '.$search->body());
            }

            $ids = collect($search->json('items', []))->pluck('id.videoId')->filter()->implode(',');
            if ($ids !== '') {
                return $this->videosById($apiKey, $ids);
            }
        }

        $trending = Http::timeout(20)->get('https://www.googleapis.com/youtube/v3/videos', [
            'part' => 'snippet,statistics',
            'chart' => 'mostPopular',
            'maxResults' => 20,
            'regionCode' => $region,
            'key' => $apiKey,
        ]);

        if (! $trending->successful()) {
            throw new \RuntimeException('YouTube mostPopular trends failed: '.$trending->body());
        }

        return $trending->json('items', []);
    }

    private function videosById(string $apiKey, string $ids): array
    {
        $videos = Http::timeout(20)->get('https://www.googleapis.com/youtube/v3/videos', [
            'part' => 'snippet,statistics',
            'id' => $ids,
            'key' => $apiKey,
        ]);

        if (! $videos->successful()) {
            throw new \RuntimeException('YouTube video detail lookup failed: '.$videos->body());
        }

        return $videos->json('items', []);
    }

    private function keywordCounts(string $text): array
    {
        $stop = array_flip(['yang','dan','ini','itu','untuk','dengan','dari','video','official','full','the','and','for','you','your','shorts','viral','terbaru']);
        $words = preg_split('/\s+/', Str::of($text)->lower()->ascii()->replaceMatches('/[^a-z0-9\s]/', ' ')->replaceMatches('/\s+/', ' ')->trim()) ?: [];
        $counts = [];
        foreach ($words as $word) {
            if (strlen($word) < 3 || isset($stop[$word]) || is_numeric($word)) {
                continue;
            }
            $counts[$word] = ($counts[$word] ?? 0) + 1;
        }
        arsort($counts);
        return $counts;
    }

    private function score(array $items, string $topic): float
    {
        if (! $items) {
            return 0;
        }

        $views = collect($items)->map(fn ($item) => (int) ($item['statistics']['viewCount'] ?? 0));
        $avgViews = max(1, (int) $views->avg());
        $base = min(92, 50 + log10($avgViews) * 7);
        return round(min(96, $base + ($topic !== '' ? 4 : 0)), 2);
    }

    private function angles(string $topic, array $keywords, array $titles): array
    {
        $keyword = $keywords[0] ?? $topic ?: 'topik ini';
        return [
            "Angle cepat: hubungkan {$keyword} dengan masalah yang paling relate untuk audience Indonesia.",
            "Angle explain: bongkar alasan kenapa {$keyword} sedang ramai dibahas.",
            "Angle reaction: mulai dari satu pernyataan kuat lalu jawab dengan contoh nyata.",
        ];
    }

    private function hooks(string $topic, array $keywords, array $titles): array
    {
        $keyword = $keywords[0] ?? $topic ?: 'hal ini';
        return [
            "Banyak orang lihat {$keyword}, tapi melewatkan bagian ini...",
            "Kalau kamu mengikuti {$keyword}, ini yang perlu kamu tahu dulu.",
            "Ini alasan {$keyword} bisa jadi konten yang gampang ditonton sampai habis.",
        ];
    }

    private function captions(string $topic, array $keywords): array
    {
        $keyword = $keywords[0] ?? $topic ?: 'trend ini';
        return [
            "Ini yang bikin {$keyword} mulai ramai dibahas. Simpan dulu biar nggak ketinggalan.",
            "Bagian paling menarik dari {$keyword} bukan cuma yang terlihat di awal.",
        ];
    }
}
