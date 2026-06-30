@extends('layouts.app', ['pageTitle' => 'Project Detail'])
@section('content')
<div class="grid gap-6 lg:grid-cols-2"><div class="ct-panel p-6"><x-status-badge :status="$project->status"/><h2 class="mt-3 text-2xl font-black text-white">{{ $project->title }}</h2><p class="mt-2 text-sm text-slate-400">Owner: {{ $project->user->email }}</p></div><div class="ct-panel p-6"><h3 class="font-bold text-white">Storage & Video</h3><p class="mt-2 text-sm text-slate-400">{{ $project->uploadedVideo?->path ?? 'No upload' }}</p></div></div>
<div class="mt-6 ct-panel p-6"><h3 class="font-bold text-white">Clips</h3><div class="mt-4 space-y-3">@foreach($project->clips as $clip)<div class="rounded-2xl border border-white/10 p-4"><div class="flex justify-between"><span class="text-white">{{ $clip->title }}</span><span class="text-cyan-300">{{ $clip->viral_score }}</span></div></div>@endforeach</div></div>
@endsection
