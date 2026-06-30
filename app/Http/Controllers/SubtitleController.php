<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSubtitleRequest;
use App\Models\Clip;
use App\Models\VideoProject;
use Illuminate\Http\RedirectResponse;

class SubtitleController extends Controller
{
    public function update(UpdateSubtitleRequest $request, VideoProject $project, Clip $clip): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($clip->project_id === $project->id, 404);
        $clip->subtitle()->updateOrCreate(
            ['clip_id' => $clip->id, 'language' => config('cliptrend.transcription_language', 'id')],
            [
                'project_id' => $project->id,
                'segments' => $request->validated('segments'),
                'style' => $request->validated('style') ?? config('cliptrend.default_subtitle_style'),
                'status' => 'edited',
            ]
        );
        return back()->with('success', 'Subtitle berhasil diperbarui.');
    }
}
