@extends('layouts.app', ['pageTitle' => 'Create New Project'])
@section('content')
<div class="grid gap-5 xl:grid-cols-[0.95fr_1.05fr]">
    <form method="POST" action="{{ route('projects.store') }}" class="ct-panel p-5">@csrf
        <div class="flex items-start justify-between gap-5">
            <div>
                <p class="ct-eyebrow">Project Setup</p>
                <h2 class="mt-1 text-2xl font-black tracking-[-0.04em] text-white">Siapkan workflow AI video</h2>
                <p class="mt-2 text-sm leading-6 text-slate-400">Tentukan sumber video dan platform output. Setelah dibuat, Anda bisa upload video dan menjalankan analisis.</p>
            </div>
            <span class="ct-pill ct-pill-brand">API-ready</span>
        </div>

        <div class="mt-6 space-y-4">
            <label class="block text-sm font-bold text-slate-300">Judul Project
                <input name="title" required class="ct-input mt-2" placeholder="Podcast motivasi episode 12">
            </label>
            <label class="block text-sm font-bold text-slate-300">Deskripsi
                <textarea name="description" rows="4" class="ct-input mt-2" placeholder="Ringkasan video, target audience, atau catatan editing..."></textarea>
            </label>
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block text-sm font-bold text-slate-300">Source Type
                    <select name="source_type" class="ct-input mt-2">
                        <option value="upload">Upload Video</option>
                        <option value="youtube">Authorized YouTube URL</option>
                    </select>
                </label>
                <label class="block text-sm font-bold text-slate-300">YouTube URL
                    <input name="youtube_url" type="url" class="ct-input mt-2" placeholder="https://youtube.com/watch?v=...">
                </label>
            </div>
            <div>
                <div class="text-sm font-bold text-slate-300">Target Platform</div>
                <div class="mt-3 grid gap-3 md:grid-cols-3">
                    @foreach(['shorts'=>'YouTube Shorts','tiktok'=>'TikTok','reels'=>'Instagram Reels'] as $value=>$label)
                        <label class="ct-soft-card flex cursor-pointer items-center gap-3 text-sm font-bold text-white transition hover:border-lime-300/30">
                            <input type="checkbox" name="target_platforms[]" value="{{ $value }}" checked class="rounded border-white/20 bg-white/10 text-lime-300 focus:ring-lime-300">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <button class="ct-button w-full md:w-auto">Create Project</button>
        </div>
    </form>

    <aside class="space-y-5">
        <div class="ct-panel p-5">
            <p class="ct-eyebrow">Output Pipeline</p>
            <h3 class="mt-1 text-xl font-black text-white">Dibuat seperti prototype</h3>
            <div class="mt-5 space-y-3">
                @foreach([
                    ['◎','AI Niche Detection','Dilakukan sebelum rendering agar template tidak generik.'],
                    ['↗','Trend Intelligence','Niche + topic + region digunakan untuk rekomendasi trend.'],
                    ['✦','AI Director','Clip scoring, hook text, retention score, dan subtitle draft.'],
                    ['⇪','Export Queue','Render final ke Shorts, TikTok, dan Reels.'],
                ] as [$icon, $title, $desc])
                    <div class="ct-table-row flex gap-4"><span class="text-xl text-lime-300">{{ $icon }}</span><div><strong class="text-white">{{ $title }}</strong><p class="mt-1 text-sm leading-6 text-slate-400">{{ $desc }}</p></div></div>
                @endforeach
            </div>
        </div>
    </aside>
</div>
@endsection
