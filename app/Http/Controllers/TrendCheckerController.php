<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrendReportRequest;
use App\Models\TrendReport;
use App\Services\Video\TrendIntelligenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrendCheckerController extends Controller
{
    public function index(Request $request): View
    {
        $reports = TrendReport::whereBelongsTo($request->user())->latest()->limit(10)->get();
        return view('trends.index', compact('reports'));
    }

    public function store(TrendReportRequest $request, TrendIntelligenceService $trends): RedirectResponse|View
    {
        try {
            $report = $trends->report($request->user(), $request->validated());
        } catch (\Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors(['topic' => $exception->getMessage()]);
        }

        $reports = TrendReport::whereBelongsTo($request->user())->latest()->limit(10)->get();

        return view('trends.index', compact('report', 'reports'));
    }
}
