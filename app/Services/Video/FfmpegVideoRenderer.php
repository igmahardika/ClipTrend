<?php

namespace App\Services\Video;

use App\Models\RenderedVideo;
use App\Models\RenderJob;
use App\Services\Contracts\VideoRendererInterface;
use App\Services\Storage\MediaPathService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class FfmpegVideoRenderer implements VideoRendererInterface
{
    private static ?bool $assFilterAvailable = null;
    private static ?bool $drawTextFilterAvailable = null;

    public function __construct(
        private readonly MediaPathService $paths,
        private readonly SubtitleFormatter $subtitles,
        private readonly WorkingMediaResolver $resolver,
    ) {}

    public function render(RenderJob $job): RenderedVideo
    {
        $job->loadMissing(['clip.subtitle', 'clip.uploadedVideo', 'clip.project']);
        $clip = $job->clip;
        $video = $clip->uploadedVideo;

        if (! $video || ! $video->path) {
            throw new \RuntimeException('Source video is not available for rendering.');
        }

        $disk = Storage::disk(config('cliptrend.media_disk'));
        $input = $this->resolver->absolutePath($video);

        $outputPath = $this->paths->rendered($clip, $job->platform);
        $output = $disk->path($outputPath);
        $subtitlePath = str_replace('.mp4', '.ass', $outputPath);
        $subtitleAbs = $disk->path($subtitlePath);

        @mkdir(dirname($output), 0775, true);
        @mkdir(dirname($subtitleAbs), 0775, true);

        $preset = $job->preset ?? [];
        $options = $preset['options'] ?? [];
        $hookText = (string) ($preset['hook_text'] ?? $clip->hook_text ?? '');
        $subtitleSegments = $preset['subtitle_segments'] ?? ($clip->subtitle?->segments ?? []);
        $assContent = $this->subtitles->toAss($subtitleSegments, $hookText, (float) $clip->duration_seconds);
        file_put_contents($subtitleAbs, $assContent);

        $renderAssPath = storage_path('app/tmp/render-'.$job->id.'.ass');
        @mkdir(dirname($renderAssPath), 0775, true);
        file_put_contents($renderAssPath, $assContent);

        $overlayDir = storage_path('app/tmp/render-overlay-'.$job->id);
        @mkdir($overlayDir, 0775, true);

        $ffmpeg = config('cliptrend.ffmpeg_path', 'ffmpeg');
        
        $mode = $options['crop_mode'] ?? config('cliptrend.default_crop_mode', 'fit_blur');
        
        if ($mode === 'smart_crop') {
            $smartCropOutput = storage_path('app/tmp/smartcrop-'.$job->id.'.mp4');
            $segmentInput = storage_path('app/tmp/segment-'.$job->id.'.mp4');
            
            // 1. Cut the segment first
            $cutProcess = new Process([
                $ffmpeg, '-hide_banner', '-y', 
                '-ss', (string) max(0, (float) $clip->start_time), 
                '-i', $input, 
                '-t', (string) max(1, (float) $clip->duration_seconds), 
                '-c:v', 'copy', '-c:a', 'copy', $segmentInput
            ]);
            $cutProcess->run();
            
            // 2. Run Python Smart Crop
            $pythonProcess = new Process([
                '/usr/bin/python3', base_path('scripts/smart_crop.py'), $segmentInput, $smartCropOutput
            ]);
            $pythonProcess->setTimeout(1800);
            $pythonProcess->run();
            
            if ($pythonProcess->isSuccessful() && file_exists($smartCropOutput)) {
                $input = $smartCropOutput;
                $clip->start_time = 0; // Segment is already cut
                $options['crop_mode'] = 'smart_crop_applied'; // Prevent videoFilter from doing static crop again
            } else {
                Log::error('Smart crop failed, falling back', ['error' => $pythonProcess->getErrorOutput()]);
                $options['crop_mode'] = 'center_crop';
            }
        }

        $vf = $this->videoFilter($options, $renderAssPath, $subtitleSegments, $hookText, (float) $clip->duration_seconds, $overlayDir);

        $cwd = $this->supportsAssFilter() ? dirname($renderAssPath) : null;

        $process = new Process([
            $ffmpeg,
            '-hide_banner',
            '-y',
            '-ss', (string) max(0, (float) $clip->start_time),
            '-i', $input,
            '-t', (string) max(1, (float) $clip->duration_seconds),
            '-map', '0:v:0',
            '-map', '0:a:0?',
            '-vf', $vf,
            '-r', '30',
            '-c:v', 'libx264',
            '-preset', config('cliptrend.render_preset', 'veryfast'),
            '-crf', (string) config('cliptrend.render_crf', 23),
            '-pix_fmt', 'yuv420p',
            '-c:a', 'aac',
            '-b:a', '160k',
            '-ar', '44100',
            '-ac', '2',
            '-movflags', '+faststart',
            '-shortest',
            $output,
        ], $cwd);
        $process->setTimeout((int) config('cliptrend.render_timeout', 3600));
        $process->run(function ($type, $buffer) use ($job) {
            $this->updateProgressFromBuffer($job, $buffer);
        });

        if (! $process->isSuccessful()) {
            Log::error('FFmpeg render failed', [
                'render_job_id' => $job->id,
                'command' => $process->getCommandLine(),
                'stderr' => $process->getErrorOutput(),
            ]);
            throw new \RuntimeException($process->getErrorOutput() ?: 'FFmpeg render failed.');
        }

        return RenderedVideo::create([
            'project_id' => $clip->project_id,
            'clip_id' => $clip->id,
            'render_job_id' => $job->id,
            'user_id' => $job->user_id,
            'platform' => $job->platform,
            'disk' => config('cliptrend.media_disk'),
            'path' => $outputPath,
            'size_bytes' => file_exists($output) ? filesize($output) : 0,
            'duration_seconds' => $clip->duration_seconds,
            'width' => 1080,
            'height' => 1920,
            'title' => $preset['title'] ?? $clip->title,
            'caption' => $preset['caption'] ?? null,
            'hashtags' => $preset['hashtags'] ?? [],
            'status' => 'ready',
        ]);
    }

    private function videoFilter(array $options, string $subtitleAbs, array $subtitleSegments, string $hookText, float $duration, string $overlayDir): string
    {
        $mode = $options['crop_mode'] ?? config('cliptrend.default_crop_mode', 'fit_blur');
        $base = match ($mode) {
            'smart_crop_applied' => 'setsar=1,fps=30',
            'center_crop', 'smart_crop' => 'scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,setsar=1,fps=30',
            'fit_blur' => 'split=2[main][bg];[bg]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,gblur=sigma=26,eq=brightness=-0.05:saturation=0.9[blur];[main]scale=1080:1920:force_original_aspect_ratio=decrease[fg];[blur][fg]overlay=(W-w)/2:(H-h)/2,setsar=1,fps=30',
            default => 'scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,setsar=1,fps=30',
        };

        $filters = [$base];

        if ($this->supportsAssFilter()) {
            $filters[] = 'ass='.$this->escapeFilterPath(basename($subtitleAbs));
        } elseif ($this->supportsDrawTextFilter()) {
            if (trim($hookText) !== '') {
                $hookFile = $overlayDir.'/hook.txt';
                file_put_contents($hookFile, $hookText);
                $filters[] = $this->drawText(
                    $hookText,
                    62,
                    '(w-text_w)/2',
                    '165',
                    'white',
                    'black@0.35',
                    0,
                    min(3.25, max(1.5, $duration)),
                    $hookFile
                );
            }

            foreach ($subtitleSegments as $index => $segment) {
                $text = trim((string) ($segment['text'] ?? ''));
                if ($text === '') {
                    continue;
                }

                $start = max(0, (float) ($segment['start'] ?? 0));
                $end = max($start + 0.2, (float) ($segment['end'] ?? ($start + 1)));
                $segmentFile = $overlayDir.'/segment-'.$index.'.txt';
                file_put_contents($segmentFile, $text);
                $filters[] = $this->drawText($text, 52, '(w-text_w)/2', 'h-260', 'white', 'black@0.35', $start, $end, $segmentFile);
            }
        }

        $filters[] = $this->progressFilter(max(1, $duration));

        $watermark = trim((string) config('cliptrend.watermark_text'));
        if ($watermark !== '' && $this->supportsDrawTextFilter()) {
            $filters[] = $this->drawText($watermark, 34, 'w-text_w-44', 'h-90', 'white@0.72', 'black@0.35');
        }

        $filters[] = 'format=yuv420p';

        return implode(',', $filters);
    }

    private function supportsAssFilter(): bool
    {
        if (self::$assFilterAvailable !== null) {
            return self::$assFilterAvailable;
        }

        $output = $this->ffmpegFilterList();
        self::$assFilterAvailable = str_contains($output, ' ass ')
            || str_contains($output, ' subtitles ');

        return self::$assFilterAvailable;
    }

    private function supportsDrawTextFilter(): bool
    {
        if (self::$drawTextFilterAvailable !== null) {
            return self::$drawTextFilterAvailable;
        }

        self::$drawTextFilterAvailable = str_contains($this->ffmpegFilterList(), ' drawtext ');

        return self::$drawTextFilterAvailable;
    }

    private function ffmpegFilterList(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $ffmpeg = (string) config('cliptrend.ffmpeg_path', 'ffmpeg');
        $process = new Process([$ffmpeg, '-filters']);
        $process->run();
        $cached = $process->getOutput().$process->getErrorOutput();

        return $cached;
    }

    private function progressFilter(float $duration): string
    {
        $duration = max(1, $duration);
        return "drawbox=x=0:y=h-16:w=iw*t/{$duration}:h=16:color=white@0.86:t=fill";
    }

    private function drawText(
        string $text,
        int $size,
        string $x,
        string $y,
        string $color,
        string $boxColor,
        ?float $start = null,
        ?float $end = null,
        ?string $textFile = null,
    ): string {
        $fontFile = config('cliptrend.subtitle_font_file');
        $parts = ['drawtext'];
        if ($fontFile && file_exists($fontFile)) {
            $parts[] = 'fontfile='.$this->escapeFilterValue($fontFile);
        }
        if ($textFile) {
            $parts[] = 'textfile='.$this->escapeFilterValue($textFile);
        } else {
            $parts[] = 'text='.$this->escapeFilterValue($text);
        }
        $parts[] = 'fontsize='.$size;
        $parts[] = 'fontcolor='.$color;
        $parts[] = 'borderw=2';
        $parts[] = 'bordercolor=black@0.65';
        $parts[] = 'box=1';
        $parts[] = 'boxcolor='.$boxColor;
        $parts[] = 'boxborderw=18';
        $parts[] = 'x='.$x;
        $parts[] = 'y='.$y;
        if ($start !== null && $end !== null) {
            $parts[] = 'enable=between(t\\,'.$start.'\\,'.$end.')';
        }

        return 'drawtext='.implode(':', array_slice($parts, 1));
    }

    private function escapeFilterValue(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim(str_replace('\\', '/', $value)));

        return str_replace(
            ['\\', "'", ':', ',', '[', ']', ';', ' '],
            ['\\\\', "\\'", '\\:', '\\,', '\\[', '\\]', '\\;', '\\ '],
            $value
        );
    }

    private function quoteFilterValue(string $value): string
    {
        return "'".$this->escapeFilterValue($value)."'";
    }

    private function escapeFilterPath(string $path): string
    {
        return $this->escapeFilterValue($path);
    }

    private function updateProgressFromBuffer(RenderJob $job, string $buffer): void
    {
        if (! preg_match('/time=(\d+):(\d+):(\d+\.\d+)/', $buffer, $matches)) {
            return;
        }
        $seconds = ((int) $matches[1] * 3600) + ((int) $matches[2] * 60) + (float) $matches[3];
        $duration = max(1, (float) $job->clip->duration_seconds);
        $progress = min(95, (int) floor(($seconds / $duration) * 100));
        if ($progress > (int) $job->progress) {
            $job->forceFill(['progress' => $progress])->saveQuietly();
        }
    }
}
