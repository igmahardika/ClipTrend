<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeVideoJob;
use App\Jobs\RenderClipJob;
use App\Models\Role;
use App\Models\User;
use App\Models\VideoProject;
use App\Services\Video\RenderingService;
use App\Services\Video\VideoIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class VideoPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->commandExists('ffmpeg') || ! $this->commandExists('ffprobe')) {
            $this->markTestSkipped('FFmpeg/FFprobe is required for pipeline tests.');
        }
    }

    public function test_upload_analyze_and_render_pipeline_completes(): void
    {
        $role = Role::create(['name' => 'User', 'slug' => 'user']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role);

        $project = VideoProject::create([
            'user_id' => $user->id,
            'title' => 'Pipeline Test Project',
            'slug' => 'pipeline-test-project',
            'source_type' => 'upload',
            'status' => 'draft',
        ]);

        $fixture = $this->createTestVideo(12);
        $upload = new UploadedFile($fixture, 'pipeline-test.mp4', 'video/mp4', null, true);

        $video = app(VideoIngestionService::class)->storeUpload($project, $upload);
        $this->assertContains($video->status, ['uploaded', 'normalized']);

        (new AnalyzeVideoJob($video))->handle(
            app(\App\Services\Video\VideoAnalysisService::class),
            app(\App\Services\Video\ClipGenerationService::class),
        );

        $project->refresh();
        $this->assertGreaterThan(0, $project->clips()->count());
        $this->assertSame('clips_ready', $project->status);

        $clip = $project->clips()->firstOrFail();
        $renderJob = app(RenderingService::class)->enqueue($clip, [
            'platform' => 'tiktok',
            'title' => $clip->title,
            'caption' => 'Test caption',
            'hashtags' => ['#shortsindonesia'],
            'hook_text' => $clip->hook_text,
            'options' => ['crop_mode' => 'fit_blur'],
        ]);

        (new RenderClipJob($renderJob))->handle(app(\App\Services\Contracts\VideoRendererInterface::class));

        $renderJob->refresh()->load('renderedVideo');
        $this->assertSame('completed', $renderJob->status);
        $this->assertNotNull($renderJob->renderedVideo);
        $this->assertTrue(
            file_exists(storage_path('app/private/'.$renderJob->renderedVideo->path))
            || file_exists(storage_path('app/'.$renderJob->renderedVideo->path))
        );
    }

    private function createTestVideo(int $seconds): string
    {
        $path = storage_path('app/tmp/pipeline-test-'.uniqid().'.mp4');
        @mkdir(dirname($path), 0775, true);

        $ffmpeg = config('cliptrend.ffmpeg_path', 'ffmpeg');
        $process = new Process([
            $ffmpeg,
            '-hide_banner',
            '-y',
            '-f', 'lavfi',
            '-i', 'testsrc=size=1280x720:rate=30',
            '-f', 'lavfi',
            '-i', 'sine=frequency=440:duration='.$seconds,
            '-t', (string) $seconds,
            '-c:v', 'libx264',
            '-pix_fmt', 'yuv420p',
            '-c:a', 'aac',
            $path,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful() || ! file_exists($path)) {
            $this->fail('Could not create test video fixture.');
        }

        return $path;
    }

    private function commandExists(string $command): bool
    {
        return trim((string) shell_exec('command -v '.escapeshellarg($command))) !== '';
    }
}
