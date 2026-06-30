<?php

namespace App\Http\Controllers;

use App\Models\RenderedVideo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OutputLibraryController extends Controller
{
    public function index(Request $request): View
    {
        $videos = RenderedVideo::whereBelongsTo($request->user())->with(['project', 'clip'])->latest()->paginate(12);
        return view('outputs.index', compact('videos'));
    }

    public function download(RenderedVideo $renderedVideo)
    {
        $this->authorize('view', $renderedVideo->project);
        $renderedVideo->update(['downloaded_at' => now()]);
        return Storage::disk($renderedVideo->disk)->download($renderedVideo->path, $renderedVideo->file_name ?? basename($renderedVideo->path));
    }
}
