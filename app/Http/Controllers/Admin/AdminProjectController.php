<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VideoProject;
use Illuminate\View\View;

class AdminProjectController extends Controller
{
    public function index(): View
    {
        $projects = VideoProject::with(['user', 'uploadedVideo', 'renderJobs'])->latest()->paginate(20);
        return view('admin.projects.index', compact('projects'));
    }

    public function show(VideoProject $project): View
    {
        $project->load(['user', 'uploadedVideo', 'primaryAnalysis.primaryNiche', 'detectedNiches', 'clips.subtitle', 'renderJobs', 'renderedVideos']);
        return view('admin.projects.show', compact('project'));
    }
}
