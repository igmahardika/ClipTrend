@extends('layouts.app', ['pageTitle' => 'Output Library'])
@section('content')
<div class="grid gap-4 lg:grid-cols-3">
@forelse($videos as $video)
    <div class="ct-card">
        <div class="overflow-hidden rounded-[22px] border border-white/10 bg-black">
            <video controls preload="metadata" class="aspect-[9/16] w-full bg-black object-contain">
                <source src="{{ route('outputs.stream', $video) }}" type="video/mp4">
            </video>
        </div>
        <div class="mt-4 flex items-center justify-between"><span class="text-sm text-cyan-300">{{ str($video->platform)->headline() }}</span><x-status-badge :status="$video->status"/></div>
        <h3 class="mt-4 text-lg font-bold text-white">{{ $video->title ?: $video->clip->title }}</h3>
        <p class="mt-2 text-sm text-slate-400">{{ $video->caption }}</p>
        <div class="mt-3 flex flex-wrap gap-2">@foreach($video->hashtags ?? [] as $tag)<span class="rounded-full bg-white/10 px-3 py-1 text-xs">{{ $tag }}</span>@endforeach</div>
        <div class="mt-5 flex flex-wrap gap-3">
            <a href="{{ route('outputs.download', $video) }}" class="ct-button inline-flex">Download</a>
            <button type="button" class="ct-button-ghost" onclick='navigator.clipboard?.writeText(@js(trim(($video->title ?? "")."\n\n".($video->caption ?? "")."\n".implode(" ", $video->hashtags ?? []))))'>Copy Pack</button>
        </div>
    </div>
@empty
    <x-empty-state class="lg:col-span-3" title="Output belum tersedia" description="Video hasil render akan muncul di sini." />
@endforelse
</div><div class="mt-6">{{ $videos->links() }}</div>
@endsection
