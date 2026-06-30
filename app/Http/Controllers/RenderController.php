<?php

namespace App\Http\Controllers;

use App\Http\Requests\RenderClipRequest;
use App\Jobs\RenderClipJob;
use App\Models\Clip;
use App\Models\RenderJob;
use App\Models\VideoProject;
use App\Services\Video\RenderingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RenderController extends Controller
{
    public function store(RenderClipRequest $request, VideoProject $project, Clip $clip, RenderingService $rendering): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($clip->project_id === $project->id, 404);
        $job = $rendering->enqueue($clip, $request->validated());
        return redirect()->route('render-jobs.show', $job)->with('success', 'Render dimulai melalui queue.');
    }

    public function show(RenderJob $renderJob): View
    {
        $this->authorize('view', $renderJob->project);
        return view('render.show', ['job' => $renderJob->load(['clip', 'renderedVideo'])]);
    }

    public function retry(RenderJob $renderJob): RedirectResponse
    {
        $this->authorize('update', $renderJob->project);
        abort_unless($renderJob->status === 'failed', 422, 'Hanya render gagal yang dapat dicoba ulang.');
        $renderJob->update(['status' => 'pending', 'progress' => 0, 'error_message' => null]);
        RenderClipJob::dispatch($renderJob)->onQueue(config('cliptrend.render_queue'));
        return back()->with('success', 'Render dicoba ulang.');
    }
}
