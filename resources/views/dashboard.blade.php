@extends('layouts.app', ['pageTitle' => 'Workspace'])
@section('content')
<div class="ct-grid-hero">
    <section class="ct-panel">
        <div class="ct-panel-header">
            <div>
                <p class="ct-eyebrow">Step 01</p>
                <h2 class="ct-panel-title">Upload / Import Video</h2>
            </div>
            <span class="ct-pill ct-pill-safe">Ready</span>
        </div>
        <div class="ct-dropzone">
            <div>
                <div class="ct-drop-icon">+</div>
                <h3 class="text-xl font-black tracking-[-0.04em] text-white">Mulai project video panjang</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-400">Buat project, upload MP4/MOV/WebM, lalu sistem menjalankan niche scan, AI director, subtitle, dan render queue.</p>
                <a href="{{ route('projects.create') }}" class="ct-button-secondary mt-5">Create New Project</a>
            </div>
        </div>
        <div class="flex items-center justify-between gap-4 px-5 pb-5">
            <div>
                <p class="text-xs text-slate-500">Source Policy</p>
                <strong class="text-white">Owned / Authorized Content</strong>
            </div>
            <span class="ct-pill ct-pill-safe">Legal-safe mode</span>
        </div>
    </section>

    <section class="ct-panel">
        <div class="ct-panel-header">
            <div>
                <p class="ct-eyebrow">Live Preview</p>
                <h2 class="ct-panel-title">Production Workflow</h2>
            </div>
            <span class="ct-pill">9:16 Output</span>
        </div>
        <div class="grid gap-5 p-5 lg:grid-cols-[1fr_260px]">
            <div class="space-y-4">
                @foreach([
                    ['01','Video Upload','Validasi format, ukuran file, metadata FFprobe, private storage.'],
                    ['02','AI Niche Detection','Mendeteksi niche, topic, audience, content style, dan alasan.'],
                    ['03','AI Director','Memilih hook, retention peak, emotional moment, dan viral score.'],
                    ['04','Render Queue','FFmpeg background job dengan progress, retry, dan output library.'],
                ] as [$num, $title, $desc])
                    <div class="ct-soft-card flex gap-4">
                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl border border-lime-300/20 bg-lime-300/10 text-sm font-black text-lime-300">{{ $num }}</div>
                        <div>
                            <h3 class="font-black text-white">{{ $title }}</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-400">{{ $desc }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="ct-phone">
                <div class="ct-phone-screen">
                    <div class="rounded-2xl bg-white/10 p-3 text-center text-sm font-black">HOOK TEXT OTOMATIS</div>
                    <div class="space-y-3">
                        <span class="ct-pill ct-pill-safe">Viral Score 91</span>
                        <div class="rounded-2xl bg-black/60 p-3 text-center text-xl font-black leading-tight">SHORT-FORM READY</div>
                        <div class="ct-progress-track"><div class="ct-progress-fill w-[78%]"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="mt-5 grid gap-4 md:grid-cols-3">
    @foreach($stats as $label => $value)
        <div class="ct-card">
            <div class="text-sm font-bold text-slate-400">{{ str($label)->headline() }}</div>
            <div class="mt-2 text-5xl font-black tracking-[-0.06em] text-white">{{ $value }}</div>
        </div>
    @endforeach
</div>

<div class="mt-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <p class="ct-eyebrow">Recent Work</p>
        <h2 class="mt-1 text-2xl font-black text-white">Recent Projects</h2>
    </div>
    <a href="{{ route('projects.index') }}" class="ct-button-ghost">View All</a>
</div>
<div class="mt-4 grid gap-4 lg:grid-cols-3">
@forelse($projects as $project)
    <a href="{{ route('projects.show', $project) }}" class="ct-card hover:border-lime-300/30 hover:bg-white/[0.08]">
        <div class="flex items-center justify-between gap-3"><x-status-badge :status="$project->status"/><span class="text-xs font-bold text-slate-500">{{ $project->created_at->format('d M Y') }}</span></div>
        <h3 class="mt-4 text-xl font-black tracking-[-0.04em] text-white">{{ $project->title }}</h3>
        <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-400">{{ $project->description ?: 'Belum ada deskripsi. Buka project untuk melanjutkan AI workflow.' }}</p>
        <div class="mt-4 flex flex-wrap gap-2">@foreach(($project->target_platforms ?? ['shorts','tiktok','reels']) as $platform)<span class="ct-pill">{{ str($platform)->headline() }}</span>@endforeach</div>
    </a>
@empty
    <x-empty-state class="lg:col-span-3" title="Belum ada project" description="Buat project pertama dan upload video panjang Anda."><a href="{{ route('projects.create') }}" class="ct-button mt-5 inline-flex">Create Project</a></x-empty-state>
@endforelse
</div>
@endsection
