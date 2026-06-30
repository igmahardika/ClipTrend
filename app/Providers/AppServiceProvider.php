<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\Contracts\{AiProviderInterface, TrendProviderInterface, VideoRendererInterface};
use App\Services\AI\AiManager;
use App\Services\Trends\TrendManager;
use App\Services\Video\FfmpegVideoRenderer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiProviderInterface::class, fn () => app(AiManager::class)->provider());
        $this->app->bind(TrendProviderInterface::class, fn () => app(TrendManager::class)->provider());
        $this->app->bind(VideoRendererInterface::class, FfmpegVideoRenderer::class);
    }

    public function boot(): void
    {
        $this->resolveRuntimePaths();
        $this->applyAdminSettings();

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(10)->by(optional($request->user())->id ?: $request->ip()));
        RateLimiter::for('analysis', fn (object $job) => Limit::perMinute(8)->by($job->userId ?? 'system'));
        RateLimiter::for('renders', fn (object $job) => Limit::perMinute(5)->by($job->userId ?? 'system'));
    }

    private function resolveRuntimePaths(): void
    {
        foreach (['ffmpeg_path' => 'ffmpeg', 'ffprobe_path' => 'ffprobe'] as $key => $binary) {
            $configured = (string) config("cliptrend.{$key}");
            if ($configured !== '' && ($this->isExecutablePath($configured) || $this->commandExists($configured))) {
                continue;
            }

            $fullBinary = '/opt/homebrew/opt/ffmpeg-full/bin/'.$binary;
            if (is_executable($fullBinary)) {
                config(["cliptrend.{$key}" => $fullBinary]);
                continue;
            }

            $detected = trim((string) shell_exec('command -v '.escapeshellarg($binary)) ?: '');
            if ($detected !== '') {
                config(["cliptrend.{$key}" => $detected]);
            }
        }

        $font = (string) config('cliptrend.subtitle_font_file');
        if ($font === '' || ! is_file($font)) {
            foreach ([
                '/System/Library/Fonts/Supplemental/Arial.ttf',
                '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
                '/Library/Fonts/Arial.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            ] as $candidate) {
                if (is_file($candidate)) {
                    config(['cliptrend.subtitle_font_file' => $candidate]);
                    config(['cliptrend.default_subtitle_style.font' => 'Arial']);
                    break;
                }
            }
        }
    }

    private function applyAdminSettings(): void
    {
        if (! class_exists(Setting::class)) {
            return;
        }

        try {
            $settings = Setting::query()->where('group', 'admin')->get()->keyBy('key');
        } catch (\Throwable) {
            return;
        }

        if ($maxUpload = $settings->get('max_upload_mb')?->value) {
            config(['cliptrend.max_upload_mb' => (int) (is_array($maxUpload) ? ($maxUpload[0] ?? 0) : $maxUpload)]);
        }

        if ($renderTimeout = $settings->get('render_timeout')?->value) {
            config(['cliptrend.render_timeout' => (int) (is_array($renderTimeout) ? ($renderTimeout[0] ?? 0) : $renderTimeout)]);
        }
    }

    private function isExecutablePath(string $path): bool
    {
        return str_contains($path, DIRECTORY_SEPARATOR) && is_executable($path);
    }

    private function commandExists(string $command): bool
    {
        if (str_contains($command, DIRECTORY_SEPARATOR)) {
            return is_executable($command);
        }

        return trim((string) shell_exec('command -v '.escapeshellarg($command))) !== '';
    }
}
