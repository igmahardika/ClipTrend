<?php

use App\Http\Controllers\Api\ProjectStatusController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/projects/{project}/status', [ProjectStatusController::class, 'project'])->name('api.projects.status');
    Route::get('/render-jobs/{renderJob}/status', [ProjectStatusController::class, 'renderJob'])->name('api.render-jobs.status');
});
