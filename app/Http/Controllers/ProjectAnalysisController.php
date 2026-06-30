<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeVideoJob;
use App\Models\VideoProject;
use Illuminate\Http\RedirectResponse;

class ProjectAnalysisController extends Controller
{
    public function store(VideoProject $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $video = $project->uploadedVideo;
        abort_unless($video, 422, 'Video belum tersedia.');
        abort_unless($video->isReadyForAnalysis(), 422, 'Video belum siap dianalisis. Aktifkan YouTube ingestion atau upload file video terlebih dahulu.');
        AnalyzeVideoJob::dispatch($video)->onQueue(config('cliptrend.analysis_queue'));
        return back()->with('success', 'Analisis ulang dimasukkan ke queue.');
    }
}
