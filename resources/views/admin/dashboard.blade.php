@extends('layouts.app', ['pageTitle' => 'Admin Dashboard'])
@section('content')
<div class="grid gap-4 md:grid-cols-4">@foreach($stats as $label=>$value)<div class="ct-card"><div class="text-sm text-slate-400">{{ str($label)->headline() }}</div><div class="mt-2 text-4xl font-black text-white">{{ $value }}</div></div>@endforeach</div>
<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="ct-panel p-6"><h3 class="font-bold text-white">Failed Render Logs</h3><div class="mt-4 space-y-3">@forelse($failedJobs as $job)<div class="rounded-2xl border border-red-400/20 bg-red-400/5 p-4"><div class="flex justify-between"><span class="text-white">#{{ $job->id }} {{ $job->platform }}</span><x-status-badge :status="$job->status"/></div><p class="mt-2 text-sm text-red-200">{{ $job->error_message }}</p></div>@empty<p class="text-sm text-slate-400">Tidak ada render gagal.</p>@endforelse</div></div>
    <div class="ct-panel p-6"><h3 class="font-bold text-white">Recent Activity</h3><div class="mt-4 space-y-3">@foreach($recentActivities as $log)<div class="rounded-2xl border border-white/10 p-4"><div class="text-sm text-white">{{ $log->action }}</div><div class="text-xs text-slate-500">{{ $log->user?->email ?? 'system' }} · {{ $log->created_at->diffForHumans() }}</div></div>@endforeach</div></div>
</div>
@endsection
