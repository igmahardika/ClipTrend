<?php

namespace App\Http\Controllers;

use App\Models\RenderJob;
use App\Models\VideoProject;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        return view('dashboard', [
            'projects' => VideoProject::whereBelongsTo($user)->latest()->limit(6)->get(),
            'stats' => [
                'projects' => VideoProject::whereBelongsTo($user)->count(),
                'completed' => VideoProject::whereBelongsTo($user)->where('status', 'completed')->count(),
                'rendering' => RenderJob::whereBelongsTo($user)->whereIn('status', ['pending', 'processing'])->count(),
            ],
        ]);
    }
}
