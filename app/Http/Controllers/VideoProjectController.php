<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVideoProjectRequest;
use App\Jobs\AnalyzeVideoJob;
use App\Models\VideoProject;
use App\Services\Video\VideoIngestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VideoProjectController extends Controller
{
    public function index(Request $request): View
    {
        $projects = VideoProject::whereBelongsTo($request->user())
            ->with(['uploadedVideo', 'detectedNiches' => fn ($q) => $q->where('is_primary', true)])
            ->latest()->paginate(12);

        return view('projects.index', compact('projects'));
    }

    public function create(): View
    {
        return view('projects.create');
    }

    public function store(StoreVideoProjectRequest $request, VideoIngestionService $ingestion): RedirectResponse
    {
        $this->authorize('create', VideoProject::class);

        $data = $request->validated();
        $project = VideoProject::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(6)),
            'description' => $data['description'] ?? null,
            'source_type' => $data['source_type'],
            'youtube_url' => $data['youtube_url'] ?? null,
            'target_platforms' => $data['target_platforms'] ?? ['shorts', 'tiktok', 'reels'],
            'status' => 'draft',
        ]);

        if (($data['source_type'] ?? null) === 'youtube') {
            $video = $ingestion->registerYouTube($project, $data['youtube_url']);

            if ($video->isReadyForAnalysis()) {
                AnalyzeVideoJob::dispatch($video)->onQueue(config('cliptrend.analysis_queue'));
            }

            $message = $video->isReadyForAnalysis()
                ? 'Project berhasil dibuat. Analisis YouTube sedang berjalan di queue.'
                : 'Project berhasil dibuat. YouTube ingestion belum aktif — upload file video di halaman project untuk melanjutkan analisis.';

            return redirect()->route('projects.show', $project)->with('success', $message);
        }

        return redirect()->route('projects.show', $project)->with('success', 'Project berhasil dibuat. Upload video atau lanjutkan analisis.');
    }

    public function show(VideoProject $project): View
    {
        $this->authorize('view', $project);
        $project->load(['uploadedVideo', 'primaryAnalysis.primaryNiche', 'detectedNiches', 'clips.subtitle', 'renderJobs', 'renderedVideos', 'clips.renderJobs']);
        $recommendation = $project->aiRecommendations()->latest()->first();
        return view('projects.show', compact('project', 'recommendation'));
    }

    public function edit(VideoProject $project): View
    {
        $this->authorize('update', $project);
        return view('projects.edit', compact('project'));
    }

    public function update(StoreVideoProjectRequest $request, VideoProject $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $project->update($request->safe()->except(['source_type', 'youtube_url']));
        return redirect()->route('projects.show', $project)->with('success', 'Project berhasil diperbarui.');
    }

    public function destroy(VideoProject $project): RedirectResponse
    {
        $this->authorize('delete', $project);
        $project->delete();
        return redirect()->route('projects.index')->with('success', 'Project dihapus.');
    }
}
