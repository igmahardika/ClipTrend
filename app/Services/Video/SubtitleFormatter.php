<?php

namespace App\Services\Video;

class SubtitleFormatter
{
    public function toSrt(array $segments): string
    {
        $srt = [];
        foreach (array_values($segments) as $index => $segment) {
            $srt[] = (string) ($index + 1);
            $srt[] = $this->srtTimestamp((float) $segment['start']).' --> '.$this->srtTimestamp((float) $segment['end']);
            $srt[] = $segment['text'] ?? '';
            $srt[] = '';
        }
        return implode(PHP_EOL, $srt);
    }

    public function toAss(array $segments, string $hookText = '', float $duration = 0): string
    {
        $font = str_replace(',', ' ', (string) config('cliptrend.default_subtitle_style.font', 'DejaVu Sans'));
        $content = [];
        $content[] = '[Script Info]';
        $content[] = 'ScriptType: v4.00+';
        $content[] = 'PlayResX: 1080';
        $content[] = 'PlayResY: 1920';
        $content[] = 'ScaledBorderAndShadow: yes';
        $content[] = '';
        $content[] = '[V4+ Styles]';
        $content[] = 'Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding';
        $content[] = 'Style: Caption,'.$font.',58,&H00FFFFFF,&H000000FF,&H00111111,&HAA000000,-1,0,0,0,100,100,0,0,3,3,0,2,70,70,230,1';
        $content[] = 'Style: Hook,'.$font.',62,&H00FFFFFF,&H000000FF,&H00111111,&HAA000000,-1,0,0,0,100,100,0,0,3,3,0,8,70,70,165,1';
        $content[] = '';
        $content[] = '[Events]';
        $content[] = 'Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text';

        if (trim($hookText) !== '') {
            $content[] = 'Dialogue: 1,'.$this->assTimestamp(0).','.$this->assTimestamp(min(3.25, max(1.5, $duration))).',Hook,,0,0,0,,'.$this->assText($hookText);
        }

        foreach ($segments as $segment) {
            $start = max(0, (float) ($segment['start'] ?? 0));
            $end = max($start + 0.2, (float) ($segment['end'] ?? ($start + 1)));
            $text = trim((string) ($segment['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $content[] = 'Dialogue: 0,'.$this->assTimestamp($start).','.$this->assTimestamp($end).',Caption,,0,0,0,,'.$this->assText($text);
        }

        return implode(PHP_EOL, $content).PHP_EOL;
    }

    private function srtTimestamp(float $seconds): string
    {
        $milliseconds = (int) (($seconds - floor($seconds)) * 1000);
        $whole = (int) floor($seconds);
        $hours = intdiv($whole, 3600);
        $minutes = intdiv($whole % 3600, 60);
        $secs = $whole % 60;
        return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $secs, $milliseconds);
    }

    private function assTimestamp(float $seconds): string
    {
        $centiseconds = (int) round(($seconds - floor($seconds)) * 100);
        $whole = (int) floor($seconds);
        $hours = intdiv($whole, 3600);
        $minutes = intdiv($whole % 3600, 60);
        $secs = $whole % 60;
        return sprintf('%d:%02d:%02d.%02d', $hours, $minutes, $secs, $centiseconds);
    }

    private function assText(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text));
        $text = str_replace(['{', '}', "\n", "\r"], ['(', ')', ' ', ' '], $text);
        return mb_strtoupper($text);
    }
}
