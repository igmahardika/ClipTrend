@extends('layouts.app', ['pageTitle' => 'Rendering Progress'])
@section('content')
<div class="ct-grid-two"
     x-data="renderJobPoller({{ $job->id }}, '{{ $job->status }}')"
     x-init="init()"
     @destroy.window="destroy()">
    <div class="ct-panel p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-black text-white">{{ $job->clip->title }}</h2>
                <p class="mt-1 text-sm text-slate-400">Platform: {{ str($job->platform)->headline() }}</p>
            </div>
            <x-status-badge :status="$job->status"/>
        </div>
        <x-progress-bar class="mt-6" :value="$job->progress" />
        <div class="mt-2 text-sm text-slate-400">{{ $job->progress }}% completed</div>

        @if($job->status === 'pending' || $job->status === 'processing')
            <div class="mt-4 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                <span class="inline-block h-3 w-3 animate-pulse rounded-full bg-lime-400"></span>
                <p class="text-sm text-slate-300">Render sedang berjalan... Halaman akan otomatis diperbarui.</p>
            </div>
        @endif

        @if($job->status === 'failed')
            <div class="mt-5 rounded-2xl border border-red-400/20 bg-red-400/10 p-4 text-sm text-red-200">
                {{ $job->error_message }}
                <form method="POST" action="{{ route('render-jobs.retry', $job) }}" class="mt-3">@csrf
                    <button class="rounded-xl bg-red-400 px-4 py-2 text-sm font-bold text-slate-950">Retry Render</button>
                </form>
            </div>
        @endif

        @if($job->renderedVideo)
            <div class="mt-6 space-y-3">
                <a href="{{ route('outputs.download', $job->renderedVideo) }}" class="ct-button inline-flex">
                    ⬇ Download Final Video
                </a>
                <a href="{{ route('outputs.index') }}" class="ct-button-ghost inline-flex ml-3">
                    Lihat Output Library
                </a>
            </div>
        @endif
    </div>
    <div class="ct-panel p-5">
        <p class="ct-eyebrow">Render Preview</p>
        <h3 class="mt-1 text-xl font-black text-white">Final 9:16 Output</h3>
        <div class="mt-5 overflow-hidden rounded-[28px] border border-white/10 bg-black">
            @if($job->renderedVideo)
                <video controls preload="metadata" class="aspect-[9/16] w-full bg-black object-contain">
                    <source src="{{ route('outputs.stream', $job->renderedVideo) }}" type="video/mp4">
                </video>
            @else
                <div class="grid aspect-[9/16] place-items-center p-8 text-center">
                    <div>
                        <span class="block text-4xl">🎬</span>
                        <p class="mt-4 text-sm text-slate-400">Preview akan tersedia setelah render selesai.</p>
                        <p class="mt-2 text-xs text-slate-500">Status: <span x-text="status" class="font-bold text-lime-300">{{ $job->status }}</span></p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
