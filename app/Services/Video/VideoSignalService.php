<?php

namespace App\Services\Video;

use App\Models\UploadedVideo;
use Symfony\Component\Process\Process;

class VideoSignalService
{
    public function __construct(private readonly WorkingMediaResolver $resolver) {}

    public function inspectSignals(UploadedVideo $video, ?string $audioPath = null): array
    {
        $input = $this->resolver->absolutePath($video);
        $hasAudio = (bool) (data_get($video->metadata, '_cliptrend.normalized_metadata.has_audio') ?? data_get($video->metadata, '_cliptrend.has_audio') ?? data_get($video->metadata, '_cliptrend.original_metadata.has_audio') ?? true);

        $signals = [
            'speech_segments' => file_exists($input) && $hasAudio ? $this->detectSpeechSegments($input, (float) ($video->duration_seconds ?? 0)) : [],
            'scene_changes' => file_exists($input) ? $this->detectSceneChanges($input) : [],
            'metadata' => [
                'duration_seconds' => (float) ($video->duration_seconds ?? 0),
                'width' => $video->width,
                'height' => $video->height,
                'frame_rate' => $video->frame_rate,
                'bitrate' => $video->bitrate,
            ],
            'multimodal' => [],
        ];

        if ($audioPath && file_exists($audioPath) && file_exists($input)) {
            $process = new Process([
                '/usr/bin/python3',
                base_path('scripts/extract_signals.py'),
                $input,
                $audioPath
            ]);
            $process->setTimeout(1800);
            $process->run();

            if ($process->isSuccessful()) {
                $signals['multimodal'] = json_decode($process->getOutput(), true) ?: [];
            } else {
                \Illuminate\Support\Facades\Log::warning('Multi-Modal Extraction Failed: ' . $process->getErrorOutput());
            }
        }

        return $signals;
    }

    private function detectSpeechSegments(string $absolutePath, float $duration): array
    {
        $process = new Process([
            config('cliptrend.ffmpeg_path', 'ffmpeg'),
            '-hide_banner',
            '-i', $absolutePath,
            '-af', 'silencedetect=noise='.config('cliptrend.silence_noise', '-35dB').':d='.config('cliptrend.silence_duration', '0.45'),
            '-f', 'null',
            '-',
        ]);
        $process->setTimeout((int) config('cliptrend.signal_timeout', 300));
        $process->run();

        $stderr = $process->getErrorOutput();
        if ($stderr === '') {
            return [];
        }

        preg_match_all('/silence_start: ([0-9.]+)/', $stderr, $starts);
        preg_match_all('/silence_end: ([0-9.]+) \| silence_duration: ([0-9.]+)/', $stderr, $ends);

        $silences = [];
        foreach ($starts[1] ?? [] as $i => $start) {
            $silences[] = [
                'start' => round((float) $start, 2),
                'end' => round((float) ($ends[1][$i] ?? $duration), 2),
            ];
        }

        if (! $silences) {
            return $duration > 0 ? [['start' => 0.0, 'end' => round($duration, 2), 'duration' => round($duration, 2)]] : [];
        }

        $segments = [];
        $cursor = 0.0;
        foreach ($silences as $silence) {
            if ($silence['start'] - $cursor >= 1.0) {
                $segments[] = ['start' => round($cursor, 2), 'end' => $silence['start'], 'duration' => round($silence['start'] - $cursor, 2)];
            }
            $cursor = max($cursor, $silence['end']);
        }

        if ($duration > 0 && $duration - $cursor >= 1.0) {
            $segments[] = ['start' => round($cursor, 2), 'end' => round($duration, 2), 'duration' => round($duration - $cursor, 2)];
        }

        return $segments;
    }

    private function detectSceneChanges(string $absolutePath): array
    {
        $process = new Process([
            config('cliptrend.ffmpeg_path', 'ffmpeg'),
            '-hide_banner',
            '-i', $absolutePath,
            '-vf', 'select=gt(scene\\,0.35),showinfo',
            '-f', 'null',
            '-',
        ]);
        $process->setTimeout((int) config('cliptrend.signal_timeout', 300));
        $process->run();

        preg_match_all('/pts_time:([0-9.]+)/', $process->getErrorOutput(), $matches);

        return collect($matches[1] ?? [])
            ->map(fn ($time) => round((float) $time, 2))
            ->unique()
            ->values()
            ->take(200)
            ->all();
    }
}
