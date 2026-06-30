<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadVideoRequest;
use App\Jobs\AnalyzeVideoJob;
use App\Models\VideoProject;
use App\Services\Video\VideoIngestionService;
use Illuminate\Http\RedirectResponse;

class VideoUploadController extends Controller
{
    public function store(UploadVideoRequest $request, VideoProject $project, VideoIngestionService $ingestion): RedirectResponse
    {
        $this->authorize('update', $project);

        $video = $ingestion->storeUpload($project, $request->file('video'));
        AnalyzeVideoJob::dispatch($video)->onQueue(config('cliptrend.analysis_queue'));

        return redirect()->route('projects.show', $project)->with('success', 'Video berhasil diupload. Analisis AI sedang berjalan di queue.');
    }
}
