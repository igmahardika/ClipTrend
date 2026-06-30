@extends('layouts.app')
@section('content')
<div class="relative min-h-screen overflow-hidden px-6 py-8">
    <nav class="mx-auto flex max-w-7xl items-center justify-between">
        <div class="ct-brand mb-0">
            <div class="ct-brand-mark">CT</div>
            <div>
                <div class="ct-brand-title">ClipTrend AI</div>
                <div class="ct-brand-subtitle">AI Shorts Studio</div>
            </div>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('login') }}" class="ct-button-ghost">Login</a>
            <a href="{{ route('register') }}" class="ct-button">Start Free</a>
        </div>
    </nav>

    <section class="mx-auto grid max-w-7xl items-center gap-12 py-20 lg:grid-cols-[1.06fr_.94fr]">
        <div>
            <div class="mb-5 inline-flex rounded-full border border-lime-300/20 bg-lime-300/10 px-4 py-2 text-sm font-bold text-lime-200">AI video repurposing platform untuk Shorts, TikTok, dan Reels</div>
            <h1 class="max-w-4xl text-5xl font-black leading-[0.93] tracking-[-0.08em] text-white lg:text-7xl">Ubah video panjang menjadi konten pendek vertikal yang siap publish.</h1>
            <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-300">Upload video atau hubungkan sumber resmi. AI mendeteksi niche, memilih momen terbaik, membuat subtitle, hook, caption, hashtag, lalu render ke format 9:16.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="ct-button">Buat Project Pertama</a>
                <a href="#features" class="ct-button-ghost">Lihat Fitur</a>
            </div>
        </div>

        <div class="ct-panel p-5">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <p class="ct-eyebrow">Live Product Preview</p>
                    <h2 class="mt-1 text-xl font-black text-white">Professional Editor Studio</h2>
                </div>
                <span class="ct-pill ct-pill-brand">Prototype-matched UI</span>
            </div>
            <div class="grid gap-5 md:grid-cols-[1fr_230px]">
                <div class="space-y-4">
                    <div class="ct-soft-card">
                        <div class="flex items-center justify-between"><span class="ct-eyebrow">Detected Niche</span><span class="ct-pill ct-pill-safe">92%</span></div>
                        <h3 class="mt-3 text-2xl font-black text-white">Podcast Motivasi</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-400">AI memilih gaya hook emosional, caption singkat, dan subtitle bold untuk short-form.</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach([['Viral', '91'], ['Retention', '88'], ['Trend', '84']] as [$label, $score])
                            <div class="ct-soft-card text-center"><div class="text-3xl font-black text-white">{{ $score }}</div><div class="mt-1 text-xs font-bold text-slate-400">{{ $label }} Score</div></div>
                        @endforeach
                    </div>
                    <div class="ct-soft-card">
                        <div class="ct-progress-track"><div class="ct-progress-fill w-4/5"></div></div>
                        <p class="mt-3 text-xs text-slate-400">Workflow: upload → niche detection → trend → AI director → preview → render.</p>
                    </div>
                </div>
                <div class="ct-phone max-w-[230px]">
                    <div class="ct-phone-screen">
                        <div class="rounded-2xl bg-white/10 p-3 text-center text-sm font-black text-white">Makin dewasa, makin berat tanggung jawabnya.</div>
                        <div class="space-y-3">
                            <span class="ct-pill ct-pill-safe">Viral Score 92</span>
                            <div class="rounded-2xl bg-black/60 p-3 text-center text-xl font-black leading-tight text-white">SEMAKIN BESAR TANGGUNG JAWAB</div>
                            <div class="ct-progress-track"><div class="ct-progress-fill w-2/3"></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="mx-auto grid max-w-7xl gap-4 pb-20 md:grid-cols-4">
        @foreach([
            ['◎','Niche Detection','Deteksi kategori, topik, audience, style, dan alasan sebelum render.'],
            ['↗','Trend Intelligence','Rekomendasi trend, hashtag, angle, opening hook, dan caption.'],
            ['✦','AI Director','Memilih clip terbaik, hook, retention moment, dan viral score.'],
            ['▣','FFmpeg Rendering','Queue background, 9:16 crop, subtitle, watermark, progress bar, retry.'],
        ] as [$icon, $feature, $desc])
            <div class="ct-card">
                <div class="mb-3 text-2xl text-lime-300">{{ $icon }}</div>
                <h3 class="font-black text-white">{{ $feature }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-400">{{ $desc }}</p>
            </div>
        @endforeach
    </section>
</div>
@endsection
