@extends('layouts.app', ['pageTitle' => 'Projects'])
@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-4">
    <div>
        <p class="ct-eyebrow">Video Project History</p>
        <h2 class="mt-1 text-2xl font-black text-white">Semua project video Anda</h2>
    </div>
    <a href="{{ route('projects.create') }}" class="ct-button">Create New Project</a>
</div>
<div class="grid gap-4 lg:grid-cols-3">
@forelse($projects as $project)
    @php($primaryNiche = $project->detectedNiches->first())
    <a href="{{ route('projects.show', $project) }}" class="ct-card hover:border-lime-300/30 hover:bg-white/[0.08]">
        <div class="flex items-center justify-between"><x-status-badge :status="$project->status"/><span class="text-xs font-bold text-slate-500">{{ $project->created_at->format('d M Y') }}</span></div>
        <h3 class="mt-4 text-xl font-black tracking-[-0.04em] text-white">{{ $project->title }}</h3>
        <p class="mt-2 text-sm leading-6 text-slate-400">{{ $project->description ?: 'Project siap masuk workflow AI Shorts Studio.' }}</p>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach($project->target_platforms ?? [] as $platform)<span class="ct-pill">{{ str($platform)->headline() }}</span>@endforeach
            @if($primaryNiche)<span class="ct-pill ct-pill-brand">{{ $primaryNiche->name }}</span>@endif
        </div>
    </a>
@empty
    <x-empty-state class="lg:col-span-3" title="Project masih kosong" description="Semua riwayat video per user akan muncul di sini."><a href="{{ route('projects.create') }}" class="ct-button mt-5 inline-flex">Create Project</a></x-empty-state>
@endforelse
</div>
<div class="mt-6">{{ $projects->links() }}</div>
@endsection
