<?php

use App\Http\Controllers\Admin\{AdminDashboardController, AdminProjectController, AdminSettingsController, AdminUserController};
use App\Http\Controllers\Auth\{AuthenticatedSessionController, RegisteredUserController};
use App\Http\Controllers\{DashboardController, LandingController, MediaPreviewController, OutputLibraryController, ProjectAnalysisController, RenderController, SubtitleController, TrendCheckerController, VideoProjectController, VideoUploadController};
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::resource('projects', VideoProjectController::class);
    Route::post('/projects/{project}/upload', [VideoUploadController::class, 'store'])->middleware('throttle:uploads')->name('projects.upload');
    Route::get('/projects/{project}/status', [App\Http\Controllers\Api\ProjectStatusController::class, 'project'])->name('projects.status');
    Route::post('/projects/{project}/analyze', [ProjectAnalysisController::class, 'store'])->name('projects.analyze');
    Route::post('/projects/{project}/clips/{clip}/subtitles', [SubtitleController::class, 'update'])->name('clips.subtitles.update');
    Route::post('/projects/{project}/clips/{clip}/render', [RenderController::class, 'store'])->name('clips.render');
    Route::get('/render-jobs/{renderJob}', [RenderController::class, 'show'])->name('render-jobs.show');
    Route::get('/render-jobs/{renderJob}/status', [App\Http\Controllers\Api\ProjectStatusController::class, 'renderJob'])->name('render-jobs.status');
    Route::post('/render-jobs/{renderJob}/retry', [RenderController::class, 'retry'])->name('render-jobs.retry');
    Route::get('/outputs', [OutputLibraryController::class, 'index'])->name('outputs.index');
    Route::get('/outputs/{renderedVideo}/download', [OutputLibraryController::class, 'download'])->name('outputs.download');
    Route::get('/outputs/{renderedVideo}/stream', [MediaPreviewController::class, 'rendered'])->name('outputs.stream');
    Route::get('/projects/{project}/source-video', [MediaPreviewController::class, 'source'])->name('projects.source-video');
    Route::get('/trends', [TrendCheckerController::class, 'index'])->name('trends.index');
    Route::post('/trends', [TrendCheckerController::class, 'store'])->name('trends.store');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
    Route::resource('users', AdminUserController::class)->only(['index', 'show', 'edit', 'update']);
    Route::resource('projects', AdminProjectController::class)->only(['index', 'show']);
    Route::get('/settings', [AdminSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
});
