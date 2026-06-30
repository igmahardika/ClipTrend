<?php

namespace App\Services\Video;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class VideoMetadataService
{
    public function inspect(string $absolutePath): array
    {
        $ffprobe = config('cliptrend.ffprobe_path', 'ffprobe');
        $process = new Process([
            $ffprobe,
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $absolutePath,
        ]);
        $process->setTimeout(90);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('ffprobe failed', ['file' => $absolutePath, 'error' => $process->getErrorOutput()]);
            return [
                'duration_seconds' => null,
                'width' => null,
                'height' => null,
                'frame_rate' => null,
                'bitrate' => null,
                'has_audio' => false,
                'has_video' => false,
                'format' => pathinfo($absolutePath, PATHINFO_EXTENSION),
                'metadata' => ['ffprobe_error' => $process->getErrorOutput()],
            ];
        }

        $payload = json_decode($process->getOutput(), true) ?: [];
        $streams = collect($payload['streams'] ?? []);
        $videoStream = $streams->first(fn ($stream) => ($stream['codec_type'] ?? null) === 'video');
        $audioStream = $streams->first(fn ($stream) => ($stream['codec_type'] ?? null) === 'audio');
        $format = $payload['format'] ?? [];

        $frameRate = $videoStream['avg_frame_rate'] ?? ($videoStream['r_frame_rate'] ?? null);
        if (is_string($frameRate) && str_contains($frameRate, '/')) {
            [$num, $den] = array_map('floatval', explode('/', $frameRate) + [1 => 1]);
            $frameRate = $den > 0 ? round($num / $den, 3) : null;
        }

        $rotation = 0;
        $sideData = $videoStream['side_data_list'] ?? [];
        foreach ($sideData as $side) {
            if (isset($side['rotation'])) {
                $rotation = (int) $side['rotation'];
                break;
            }
        }
        if (! $rotation && isset($videoStream['tags']['rotate'])) {
            $rotation = (int) $videoStream['tags']['rotate'];
        }

        $width = $videoStream['width'] ?? null;
        $height = $videoStream['height'] ?? null;
        if (in_array(abs($rotation), [90, 270], true)) {
            [$width, $height] = [$height, $width];
        }

        return [
            'duration_seconds' => isset($format['duration']) ? round((float) $format['duration'], 2) : null,
            'width' => $width,
            'height' => $height,
            'frame_rate' => $frameRate ? (string) $frameRate : null,
            'bitrate' => isset($format['bit_rate']) ? (int) $format['bit_rate'] : null,
            'has_audio' => (bool) $audioStream,
            'has_video' => (bool) $videoStream,
            'format' => $format['format_name'] ?? pathinfo($absolutePath, PATHINFO_EXTENSION),
            'audio_codec' => $audioStream['codec_name'] ?? null,
            'video_codec' => $videoStream['codec_name'] ?? null,
            'rotation' => $rotation,
            'metadata' => array_merge($payload, [
                '_cliptrend' => [
                    'has_audio' => (bool) $audioStream,
                    'has_video' => (bool) $videoStream,
                    'audio_codec' => $audioStream['codec_name'] ?? null,
                    'video_codec' => $videoStream['codec_name'] ?? null,
                    'rotation' => $rotation,
                ],
            ]),
        ];
    }
}
