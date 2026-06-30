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
        $platform = (string) ($filters['platform'] ?? 'tiktok');
        $seed = $topic !== '' ? $topic : 'konten short-form Indonesia';

        $keywords = collect(preg_split('/\s+/', Str::of($seed)->lower()->ascii()->replaceMatches('/[^a-z0-9\s]/', ' ')->trim()) ?: [])
            ->filter(fn ($word) => strlen($word) >= 3)
            ->take(6)
            ->values()
            ->all();

        if ($keywords === []) {
            $keywords = ['motivasi', 'edukasi', 'tips', 'viral'];
        }

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
                'provider' => 'local_heuristic',
                'platform_target' => $platform,
                'region' => $region,
                'topic' => $topic,
                'notice' => 'Local trend heuristics used because no external trend API key is configured.',
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
