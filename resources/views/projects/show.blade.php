@extends('layouts.app', ['pageTitle' => $project->title])
@section('content')
@php
    $video = $project->uploadedVideo;
    $primaryNiche = $project->detectedNiches->firstWhere('is_primary', true) ?? $project->detectedNiches->first();
    $analysis = $project->primaryAnalysis;
    $confidence = $primaryNiche ? (int) round($primaryNiche->confidence_score) : null;
    $topClip = $project->clips->sortByDesc('viral_score')->first();
    $renderingJobs = $project->renderJobs->whereIn('status', ['pending','processing']);
@endphp
<div x-data="projectStatusPoller({{ $project->id }}, '{{ $video?->status ?? 'waiting' }}', '{{ $project->niche_detection_status ?? 'waiting' }}')">
<div class="mb-5 flex flex-wrap items-center justify-between gap-4">
    <a href="{{ route('projects.index') }}" class="ct-button-ghost">← Kembali ke Projects</a>
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('projects.edit', $project) }}" class="ct-button-secondary">Edit Project</a>
        <form action="{{ route('projects.destroy', $project) }}" method="POST"
              onsubmit="return confirm('Apakah Anda yakin ingin menghapus project ini? Semua file render dan data analisis terkait akan ikut terhapus.')">
            @csrf
            @method('DELETE')
            <button class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm font-bold text-red-400 hover:bg-red-500 hover:text-white transition">
                Hapus Project
            </button>
        </form>
    </div>
</div>

<section class="ct-grid-hero" id="workspace">
    <div class="ct-panel">
        <div class="ct-panel-header">
            <div>
                <p class="ct-eyebrow">Step 01</p>
                <h2 class="ct-panel-title">Upload / Import Video</h2>
            </div>
            <x-status-badge :status="$video?->status ?? 'waiting'" />
        </div>

        @if($video && $video->status === 'pending_ingest')
            <div class="m-5 rounded-[22px] border border-amber-300/20 bg-amber-300/10 p-5 text-amber-50">
                <h3 class="font-black text-white">YouTube ingestion belum aktif</h3>
                <p class="mt-2 text-sm leading-6 text-amber-50/90">{{ $video->metadata['notice'] ?? 'Set YOUTUBE_INGESTION_ENABLED=true dan pasang yt-dlp untuk download otomatis.' }}</p>
                @if($project->youtube_url)
                    <p class="mt-2 text-xs text-amber-50/70">URL tersimpan: {{ $project->youtube_url }}</p>
                @endif
            </div>
            <form method="POST" action="{{ route('projects.upload', $project) }}" enctype="multipart/form-data" class="ct-dropzone mx-5 mb-5"
                  x-data="uploadProgress()" @submit="startUpload">
                @csrf
                <div>
                    <div class="ct-drop-icon">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-black tracking-[-0.04em] text-white">Upload video sebagai alternatif</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-400">Atau aktifkan YouTube ingestion di .env, lalu buat project baru dengan URL YouTube.</p>
                    <input type="file" name="video" required accept="video/*" class="ct-input mt-5"
                           @change="onFileSelect" :disabled="uploading">
                    <p x-show="fileName" x-text="'File: ' + fileName" class="mt-2 text-xs text-lime-300"></p>
                    <div x-show="uploading" class="mt-5 w-full overflow-hidden rounded-full bg-white/10">
                        <div class="h-2 rounded-full bg-gradient-to-r from-lime-400 to-emerald-400 transition-all duration-700"
                             :style="'width: ' + progress + '%'"></div>
                    </div>
                    <p x-show="uploading" class="mt-1 text-center text-xs text-slate-400" x-text="'Uploading... ' + progress + '%'"></p>
                    <button class="ct-button-secondary mt-5" :disabled="uploading">
                        <span x-show="!uploading">Upload &amp; Analyze</span>
                        <span x-show="uploading">⏳ Uploading...</span>
                    </button>
                </div>
            </form>
        @elseif($video)
            <div class="m-5 rounded-[22px] border border-white/10 bg-black/20 p-5">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="ct-eyebrow">Source File</p>
                        <h3 class="mt-1 text-xl font-black text-white">{{ $video->original_filename }}</h3>
                        <p class="mt-2 text-sm text-slate-400">{{ $video->width ?: '—' }}x{{ $video->height ?: '—' }} · {{ number_format(($video->size_bytes ?? 0)/1024/1024,2) }} MB · {{ $video->duration_seconds ?: '—' }} sec</p>
                    </div>
                    <span class="ct-pill ct-pill-safe">Private Storage</span>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('projects.upload', $project) }}" enctype="multipart/form-data" class="ct-dropzone"
                  x-data="uploadProgress()" @submit="startUpload">
                @csrf
                <div>
                    <div class="ct-drop-icon">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-black tracking-[-0.04em] text-white">Drop video panjang di sini</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-400">MP4, MOV, MKV, WebM. Setelah upload, sistem menjalankan metadata extraction dan AI analysis queue.</p>
                    <input type="file" name="video" required accept="video/*" class="ct-input mt-5"
                           @change="onFileSelect" :disabled="uploading">
                    <p x-show="fileName" x-text="'File: ' + fileName" class="mt-2 text-xs text-lime-300"></p>
                    <div x-show="uploading" class="mt-5 w-full overflow-hidden rounded-full bg-white/10">
                        <div class="h-2 rounded-full bg-gradient-to-r from-lime-400 to-emerald-400 transition-all duration-700"
                             :style="'width: ' + progress + '%'"></div>
                    </div>
                    <p x-show="uploading" class="mt-1 text-center text-xs text-slate-400" x-text="'Uploading... ' + progress + '%'"></p>
                    <button class="ct-button-secondary mt-5" :disabled="uploading">
                        <span x-show="!uploading">Upload &amp; Analyze</span>
                        <span x-show="uploading">⏳ Uploading...</span>
                    </button>
                </div>
            </form>
        @endif

        <div class="flex items-center justify-between gap-4 px-5 pb-5">
            <div>
                <p class="text-xs text-slate-500">Source Policy</p>
                <strong class="text-white">Owned / Authorized Content</strong>
            </div>
            <span class="ct-pill ct-pill-safe">Legal-safe mode</span>
        </div>
    </div>

    <div class="ct-panel">
        <div class="ct-panel-header">
            <div>
                <p class="ct-eyebrow">Live Preview</p>
                <h2 class="ct-panel-title">Render Preview</h2>
            </div>
            <span class="ct-pill">9:16 Studio</span>
        </div>
        <div class="grid gap-5 p-5 lg:grid-cols-[1fr_270px]">
            <div class="ct-video-stage m-0">
                @if($video && $video->status !== 'pending_ingest')
                    <video controls preload="metadata" class="h-full w-full bg-black object-contain">
                        <source src="{{ route('projects.source-video', $project) }}" type="{{ $video->mime_type ?: 'video/mp4' }}">
                        Browser Anda belum mendukung preview video.
                    </video>
                @else
                    <div class="ct-empty-video">
                        <span class="grid h-16 w-16 place-items-center rounded-full border border-white/10 bg-white/5 text-2xl">▶</span>
                        <p class="text-center text-sm">Preview source/render akan muncul setelah pipeline aktif</p>
                    </div>
                @endif
            </div>
            <div class="ct-phone max-w-[270px]">
                <div class="ct-phone-screen">
                    <div class="rounded-2xl bg-white/10 p-3 text-center text-sm font-black text-white">{{ $topClip?->hook_text ?: 'Hook text akan dibuat oleh AI Director' }}</div>
                    <div class="space-y-3">
                        <span class="ct-pill ct-pill-safe">Viral {{ $topClip ? (int) $topClip->viral_score : '—' }}</span>
                        <div class="rounded-2xl bg-black/60 p-3 text-center text-xl font-black leading-tight text-white">{{ $topClip?->title ?: 'SHORT-FORM READY' }}</div>
                        <div class="ct-progress-track"><div class="ct-progress-fill" style="width: {{ $topClip ? min(100, (int) $topClip->viral_score) : 36 }}%"></div></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="ct-meta-grid">
            <div class="ct-meta-item"><span class="text-xs text-slate-500">Duration</span><strong class="mt-1 block text-white">{{ $video?->duration_seconds ?: '—' }} sec</strong></div>
            <div class="ct-meta-item"><span class="text-xs text-slate-500">Format</span><strong class="mt-1 block text-white">16:9 → 9:16</strong></div>
            <div class="ct-meta-item"><span class="text-xs text-slate-500">Language</span><strong class="mt-1 block text-white">Bahasa Indonesia</strong></div>
        </div>
    </div>
</section>

@if(($project->status === 'failed' || ($video?->status === 'failed')) && ($video?->metadata['analysis_error'] ?? null))
    <div class="mt-5 rounded-[24px] border border-red-400/20 bg-red-400/10 p-5 text-red-100">
        <div class="flex items-start gap-3">
            <div class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-red-400/20 font-black">!</div>
            <div>
                <h3 class="font-black text-white">Real analysis gagal dijalankan</h3>
                <p class="mt-2 text-sm leading-6 text-red-100/90">{{ $video->metadata['analysis_error'] }}</p>
                <p class="mt-2 text-xs leading-5 text-red-100/70">V7 tidak membuat dummy transcript. Pastikan OPENAI_API_KEY diisi atau WHISPER_BIN mengarah ke local Whisper, lalu klik Analyze Again.</p>
                <form method="POST" action="{{ route('projects.analyze', $project) }}" class="mt-4">@csrf<button class="rounded-xl bg-red-300 px-4 py-2 text-sm font-black text-slate-950">Analyze Again</button></form>
            </div>
        </div>
    </div>
@endif

<section class="ct-grid-two mt-5" id="niche">
    <div class="ct-panel">
        <div class="ct-panel-header">
            <div>
                <p class="ct-eyebrow">Step 02</p>
                <h2 class="ct-panel-title">AI Niche Detection</h2>
            </div>
            <x-status-badge :status="$project->niche_detection_status ?? ($primaryNiche ? 'completed' : 'waiting')" />
        </div>
        <div class="flex flex-col gap-5 p-5 md:flex-row md:items-center">
            <div class="ct-score-ring">
                <div>
                    <strong class="block text-3xl font-black text-white">{{ $confidence ? $confidence.'%' : '—' }}</strong>
                    <span class="text-xs font-bold text-slate-400">confidence</span>
                </div>
            </div>
            <div>
                <p class="ct-eyebrow">Detected Niche</p>
                <h3 class="mt-1 text-2xl font-black tracking-[-0.04em] text-white">
                    @if($video?->status === 'analyzing')
                        ⏳ Sedang Menganalisis Video...
                    @else
                        {{ $primaryNiche?->name ?: 'Belum ada video dianalisis' }}
                    @endif
                </h3>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">
                    @if($video?->status === 'analyzing')
                        Proses transkripsi audio offline (Whisper) dan ekstraksi sinyal AI sedang berjalan secara otomatis di latar belakang. Mohon tunggu, halaman akan memuat ulang secara otomatis begitu hasil siap.
                    @else
                        {{ $primaryNiche?->reasoning ?: 'Upload video, lalu sistem akan membaca sinyal dari transcript, visual cue, audio cue, metadata, dan trend context sebelum proses rendering.' }}
                    @endif
                </p>
            </div>
        </div>
        <div class="grid gap-3 px-5 pb-5 md:grid-cols-3">
            <div class="ct-soft-card"><span class="text-xs font-bold text-slate-500">Content Intent</span><strong class="mt-2 block text-white">{{ $analysis?->content_intent ?? '—' }}</strong><p class="mt-2 text-xs leading-5 text-slate-400">Tujuan utama konten yang diprediksi AI.</p></div>
            <div class="ct-soft-card"><span class="text-xs font-bold text-slate-500">Best Edit Style</span><strong class="mt-2 block text-white">{{ $analysis?->style ?? 'Creator Pro' }}</strong><p class="mt-2 text-xs leading-5 text-slate-400">Preset editing yang disarankan sebelum render.</p></div>
            <div class="ct-soft-card"><span class="text-xs font-bold text-slate-500">Render Readiness</span><strong class="mt-2 block text-white">{{ $primaryNiche ? 'Ready' : 'Not Ready' }}</strong><p class="mt-2 text-xs leading-5 text-slate-400">Render ideal dilakukan setelah niche terkunci.</p></div>
        </div>
        <div class="px-5 pb-5">
            @if($video?->status === 'analyzing')
                <div class="ct-table-row flex gap-4 border-lime-300/20 bg-lime-300/5 animate-pulse">
                    <span class="text-sm font-black text-lime-300">⏳</span>
                    <p class="text-sm leading-6 text-slate-300">Mengekstrak transkrip audio, memetakan sinyal konten, dan menghitung skor viral... (Sedang berjalan otomatis)</p>
                </div>
            @elseif($primaryNiche && $primaryNiche->signals)
                <div class="grid gap-3">
                    @foreach($primaryNiche->signals as $i => $signal)
                        <div class="ct-table-row flex gap-4"><span class="text-sm font-black text-lime-300">{{ str_pad($i+1, 2, '0', STR_PAD_LEFT) }}</span><p class="text-sm leading-6 text-slate-300">{{ $signal }}</p></div>
                    @endforeach
                </div>
            @else
                <div class="ct-table-row flex gap-4"><span class="text-sm font-black text-slate-500">01</span><p class="text-sm leading-6 text-slate-400">Belum ada sinyal. Upload video untuk memulai niche scan.</p></div>
            @endif
            <form method="POST" action="{{ route('projects.analyze', $project) }}" class="mt-5 flex flex-wrap gap-3" @submit="isScanning = true">@csrf
                @if($video?->status === 'analyzing')
                    <button class="ct-button-secondary" type="button" disabled>
                        <span class="inline-block animate-spin mr-1">⏳</span> Menganalisis Video...
                    </button>
                @elseif($video?->isReadyForAnalysis())
                    <button class="ct-button-secondary" :disabled="isScanning">
                        <span x-show="!isScanning">Run Niche Scan</span>
                        <span x-show="isScanning" class="flex items-center gap-2">
                            <span class="inline-block animate-spin">⏳</span> Scanning Niche...
                        </span>
                    </button>
                @else
                    <button class="ct-button-ghost" type="button" disabled>Upload video dulu untuk menjalankan niche scan</button>
                @endif
                <button class="ct-button" type="button" disabled>{{ $primaryNiche ? 'Detected Niche Applied' : 'Apply Detected Niche' }}</button>
            </form>
        </div>
    </div>

    <div class="ct-panel">
        <div class="ct-panel-header">
            <div>
                <p class="ct-eyebrow">AI Classification</p>
                <h2 class="ct-panel-title">Niche Candidates</h2>
            </div>
        </div>
        <div class="space-y-3 p-5">
            @forelse($project->detectedNiches as $niche)
                <div class="ct-table-row relative overflow-hidden {{ $niche->is_primary ? 'border-lime-300/30 bg-lime-300/10' : '' }}">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <strong class="text-white">{{ $niche->name }}</strong>
                            <p class="mt-1 text-xs leading-5 text-slate-400">{{ $niche->reasoning }}</p>
                        </div>
                        <span class="ct-pill {{ $niche->is_primary ? 'ct-pill-brand' : '' }}">{{ (int) $niche->confidence_score }}%</span>
                    </div>
                    <!-- Horizontal Micro Progress Bar (2px height) -->
                    <div class="absolute bottom-0 left-0 right-0 h-[2px] bg-white/5">
                        <div class="h-full transition-all duration-500" 
                             style="width: {{ $niche->confidence_score }}%; background: {{ $niche->is_primary ? 'linear-gradient(90deg, #c0ff3e, #00f0ff)' : 'rgba(255,255,255,0.15)' }}">
                        </div>
                    </div>
                </div>
            @empty
                <div class="ct-table-row"><strong class="text-white">Waiting analysis</strong><p class="mt-1 text-sm text-slate-400">Candidate niche akan muncul setelah video dipilih.</p></div>
            @endforelse
            <div class="rounded-[22px] border border-lime-300/15 bg-lime-300/[0.06] p-5">
                <h3 class="font-black text-white">Kenapa ini penting sebelum render?</h3>
                <p class="mt-2 text-sm leading-6 text-slate-400">Niche menentukan hook, subtitle style, pacing, crop strategy, caption, hashtag, dan template. Dengan niche detection, hasil editing tidak terasa generik.</p>
            </div>
        </div>
    </div>
</section>

<section class="ct-grid-two mt-5" id="director">
    <div class="ct-panel">
        <div class="ct-panel-header">
            <div>
                <p class="ct-eyebrow">Step 03</p>
                <h2 class="ct-panel-title">AI Director — Clip Selection</h2>
            </div>
            <span class="ct-pill ct-pill-brand">Smart scoring</span>
        </div>
        <div class="space-y-4 p-5">
            @forelse($project->clips as $clip)
                <div class="rounded-[24px] border border-white/10 bg-white/[0.04] p-4">
                    <div class="grid gap-4 xl:grid-cols-[1fr_300px]">
                        <div>
                            <div class="flex flex-wrap items-center gap-3"><span class="ct-pill ct-pill-safe">Viral {{ (int) $clip->viral_score }}</span><span class="ct-pill">{{ $clip->start_time }}s → {{ $clip->end_time }}s</span></div>
                            <h3 class="mt-4 text-xl font-black tracking-[-0.04em] text-white">{{ $clip->title }}</h3>
                            <p class="mt-2 text-sm font-bold text-lime-200">{{ $clip->hook_text }}</p>
                            <p class="mt-2 text-sm leading-6 text-slate-400">{{ $clip->transcript_excerpt }}</p>
                            <div class="mt-4 grid gap-3 md:grid-cols-2">
                                <div><div class="mb-1 flex justify-between text-xs text-slate-400"><span>Retention</span><span>{{ (int) $clip->retention_score }}%</span></div><div class="ct-progress-track"><div class="ct-progress-fill" style="width: {{ min(100, (int)$clip->retention_score) }}%"></div></div></div>
                                <div><div class="mb-1 flex justify-between text-xs text-slate-400"><span>Viral Potential</span><span>{{ (int) $clip->viral_score }}%</span></div><div class="ct-progress-track"><div class="ct-progress-fill" style="width: {{ min(100, (int)$clip->viral_score) }}%"></div></div></div>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('clips.render', [$project, $clip]) }}" class="rounded-[22px] border border-white/10 bg-slate-950/70 p-4">@csrf
                            <p class="ct-eyebrow">Render Setup</p>
                            <label class="mt-3 block text-xs font-bold text-slate-400">Platform
                                <select name="platform" class="ct-input mt-1"><option value="tiktok">TikTok</option><option value="shorts">YouTube Shorts</option><option value="reels">Instagram Reels</option></select>
                            </label>
                            <label class="mt-3 block text-xs font-bold text-slate-400">Title
                                <input name="title" value="{{ $clip->title }}" class="ct-input mt-1">
                            </label>
                            <label class="mt-3 block text-xs font-bold text-slate-400">Caption
                                <textarea name="caption" rows="3" class="ct-input mt-1" placeholder="Caption...">{{ $recommendation?->content['caption'] ?? '' }}</textarea>
                            </label>
                            @php($renderHashtags = $recommendation?->content['hashtags'] ?? [])
                            @forelse($renderHashtags as $tag)<input type="hidden" name="hashtags[]" value="{{ $tag }}">@empty<input type="hidden" name="hashtags[]" value="#shortsindonesia">@endforelse
                            <input type="hidden" name="hook_text" value="{{ $clip->hook_text }}">
                            <label class="mt-3 block text-xs font-bold text-slate-400">Crop Mode
                                <select name="options[crop_mode]" class="ct-input mt-1">
                                    <option value="smart_crop" selected>Smart Crop (AI Face Tracking)</option>
                                    <option value="fit_blur">Fit & Blur Background</option>
                                    <option value="center_crop">Static Center Crop</option>
                                </select>
                            </label>
                            <label class="mt-4 flex items-center gap-2 text-xs font-bold text-lime-400 cursor-pointer">
                                <input type="checkbox" name="options[auto_jumpcut]" value="1" checked class="rounded border-white/20 bg-black/40 text-lime-500 focus:ring-lime-500/30">
                                <span>Auto Jump-Cut (Hapus Jeda Hening)</span>
                            </label>
                            @if($video && $video->status !== 'pending_ingest')
                                <button class="ct-button mt-4 w-full">Render Clip</button>
                            @else
                                <button class="ct-button-ghost mt-4 w-full" type="button" disabled>Aktifkan authorized YouTube ingestion atau upload file untuk render</button>
                            @endif
                        </form>
                    </div>
                    @if($clip->subtitle)
                        <details class="mt-4 rounded-2xl border border-white/10 bg-black/20 p-4">
                            <summary class="cursor-pointer text-sm font-black text-lime-300">Subtitle Editor</summary>
                            <form method="POST" action="{{ route('clips.subtitles.update', [$project, $clip]) }}" class="mt-4 space-y-2">@csrf
                                @foreach($clip->subtitle->segments ?? [] as $i => $seg)
                                    <div class="grid gap-2 md:grid-cols-[110px_110px_1fr] p-2.5 rounded-xl bg-white/[0.02] border border-white/5 transition-all duration-300 hover:bg-white/[0.04] hover:border-lime-400/20">
                                        <div class="flex items-center gap-1.5 px-2 bg-white/5 rounded-lg border border-white/5 h-11">
                                            <span class="text-[9px] uppercase font-bold text-slate-500">In</span>
                                            <input name="segments[{{ $i }}][start]" value="{{ $seg['start'] }}" class="w-full bg-transparent text-xs font-mono text-lime-400 focus:outline-none">
                                        </div>
                                        <div class="flex items-center gap-1.5 px-2 bg-white/5 rounded-lg border border-white/5 h-11">
                                            <span class="text-[9px] uppercase font-bold text-slate-500">Out</span>
                                            <input name="segments[{{ $i }}][end]" value="{{ $seg['end'] }}" class="w-full bg-transparent text-xs font-mono text-lime-400 focus:outline-none">
                                        </div>
                                        <input name="segments[{{ $i }}][text]" value="{{ $seg['text'] }}" class="w-full bg-white/5 border border-white/5 rounded-lg px-3 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none focus:border-lime-400 focus:bg-white/[0.08] focus:ring-2 focus:ring-lime-400/20 h-11">
                                    </div>
                                @endforeach
                                <button class="ct-button-secondary">Save Subtitle</button>
                            </form>
                        </details>
                    @endif
                </div>
            @empty
                @if($video?->status === 'analyzing')
                    <div class="rounded-[22px] border border-lime-300/20 bg-lime-300/5 p-8 text-center animate-pulse">
                        <span class="text-3xl block">⏳</span>
                        <h3 class="mt-3 text-lg font-black text-white">Menunggu Analisis Video Selesai</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-400">AI Director akan memotong video menjadi klip-klip viral secara otomatis begitu transkrip siap.</p>
                    </div>
                @else
                    <x-empty-state title="Clip belum tersedia" description="Setelah analisis selesai, AI Director akan membuat kandidat clip terbaik." />
                @endif
            @endforelse
        </div>
    </div>

    <aside class="space-y-5">
        <div class="ct-panel p-5">
            <p class="ct-eyebrow">AI Copy Pack</p>
            @if($recommendation)
                <h3 class="mt-3 text-xl font-black text-white">{{ $recommendation->title }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-400">{{ $recommendation->content['caption'] ?? '' }}</p>
                <div class="mt-4 flex flex-wrap gap-2">@foreach($recommendation->content['hashtags'] ?? [] as $tag)<span class="ct-pill">{{ $tag }}</span>@endforeach</div>
            @else
                <p class="mt-3 text-sm leading-6 text-slate-400">Belum ada rekomendasi AI. Jalankan analisis untuk menghasilkan title, caption, hashtag, dan keyword.</p>
            @endif
        </div>
        <div class="ct-panel p-5">
            <p class="ct-eyebrow">Export Queue</p>
            <h3 class="mt-1 text-xl font-black text-white">Render Jobs</h3>
            <div class="mt-4 space-y-3">
                @forelse($project->renderJobs as $job)
                    <a href="{{ route('render-jobs.show', $job) }}" class="ct-table-row block hover:border-lime-300/30">
                        <div class="flex justify-between gap-3"><span class="text-sm font-black text-white">{{ str($job->platform)->headline() }}</span><x-status-badge :status="$job->status"/></div>
                        <x-progress-bar class="mt-3" :value="$job->progress"/>
                    </a>
                @empty
                    <p class="text-sm text-slate-400">Belum ada render job.</p>
                @endforelse
            </div>
        </div>
    </aside>
</section>
</div>
@endsection
