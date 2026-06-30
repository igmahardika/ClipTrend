<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\RenderedVideo;
use App\Models\RenderJob;
use App\Models\User;
use App\Models\VideoProject;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'users' => User::count(),
                'projects' => VideoProject::count(),
                'rendering' => RenderJob::whereIn('status', ['pending', 'processing'])->count(),
                'outputs' => RenderedVideo::count(),
            ],
            'failedJobs' => RenderJob::where('status', 'failed')->latest()->limit(8)->get(),
            'recentActivities' => ActivityLog::with('user')->latest()->limit(12)->get(),
            'storageDisk' => config('cliptrend.media_disk'),
        ]);
    }
}
