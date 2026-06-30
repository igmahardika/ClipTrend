@extends('layouts.app', ['pageTitle' => 'Trend Intelligence'])
@section('content')
<div class="ct-grid-two">
    <form method="POST" action="{{ route('trends.store') }}" class="ct-panel p-5">@csrf
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="ct-eyebrow">Step Trend</p>
                <h2 class="mt-1 text-2xl font-black tracking-[-0.04em] text-white">Trend Intelligence</h2>
                <p class="mt-2 text-sm leading-6 text-slate-400">Modular adapter siap diganti TikTok Creative Center, Google Trends, YouTube Data API, atau layanan AI lainnya.</p>
            </div>
            <span class="ct-pill ct-pill-warn">Standby</span>
        </div>
        <div class="mt-6 space-y-4">
            <label class="block text-sm font-bold text-slate-300">Niche
                <input name="niche" class="ct-input mt-2" placeholder="Podcast Motivasi">
            </label>
            <label class="block text-sm font-bold text-slate-300">Topik
                <input name="topic" class="ct-input mt-2" placeholder="tanggung jawab, karier, bisnis">
            </label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm font-bold text-slate-300">Platform
                    <select name="platform" class="ct-input mt-2"><option value="tiktok">TikTok</option><option value="shorts">Shorts</option><option value="reels">Reels</option></select>
                </label>
                <label class="block text-sm font-bold text-slate-300">Region
                    <input name="region" value="ID" class="ct-input mt-2">
                </label>
            </div>
            <button class="ct-button w-full">Check Trend</button>
        </div>
    </form>

    <div class="space-y-5">
        @if(isset($report))
            <div class="ct-panel p-5">
                <div class="flex items-start justify-between gap-5">
                    <div>
                        <p class="ct-eyebrow">Trend Report</p>
                        <h3 class="mt-1 text-2xl font-black tracking-[-0.04em] text-white">{{ $report->niche ?: $report->topic }}</h3>
                        <p class="mt-2 text-sm text-slate-400">{{ str($report->platform)->headline() }} · {{ $report->region }}</p>
                    </div>
                    <div class="ct-score-ring h-24 w-24"><div><strong class="block text-3xl font-black text-white">{{ $report->score }}</strong><span class="text-xs text-slate-400">score</span></div></div>
                </div>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div class="ct-soft-card"><h4 class="font-black text-white">Hashtags</h4><div class="mt-3 flex flex-wrap gap-2">@foreach($report->hashtags ?? [] as $tag)<span class="ct-pill">{{ $tag }}</span>@endforeach</div></div>
                    <div class="ct-soft-card"><h4 class="font-black text-white">Opening Hooks</h4><ul class="mt-3 space-y-2 text-sm leading-6 text-slate-300">@foreach($report->hooks ?? [] as $hook)<li>• {{ $hook }}</li>@endforeach</ul></div>
                </div>
            </div>
        @else
            <div class="ct-panel p-5">
                <p class="ct-eyebrow">Recommendation Preview</p>
                <h3 class="mt-1 text-xl font-black text-white">Cek trend sebelum render</h3>
                <p class="mt-2 text-sm leading-6 text-slate-400">Gunakan niche dan topik untuk menghasilkan angle konten, hashtag, opening hook, dan caption pendek yang relevan.</p>
            </div>
        @endif

        <div class="ct-panel p-5">
            <p class="ct-eyebrow">History</p>
            <h3 class="mt-1 text-xl font-black text-white">Recent Reports</h3>
            <div class="mt-4 space-y-3">
                @forelse($reports as $item)
                    <div class="ct-table-row">
                        <div class="flex justify-between gap-4"><span class="font-black text-white">{{ $item->niche ?: $item->topic }}</span><span class="ct-pill ct-pill-brand">{{ $item->score }}</span></div>
                        <p class="mt-1 text-xs text-slate-500">{{ $item->platform }} · {{ $item->region }} · {{ $item->created_at->diffForHumans() }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">Belum ada report.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
