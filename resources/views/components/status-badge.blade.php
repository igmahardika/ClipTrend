@props(['status' => 'draft'])
@php
$map = [
    'draft' => 'bg-slate-800 text-slate-300 ring-slate-700',
    'uploaded' => 'bg-blue-500/10 text-blue-300 ring-blue-500/20',
    'analyzing' => 'bg-amber-500/10 text-amber-300 ring-amber-500/20',
    'analyzed' => 'bg-cyan-500/10 text-cyan-300 ring-cyan-500/20',
    'clips_ready' => 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/20',
    'waiting' => 'bg-slate-800 text-slate-400 ring-slate-700',
    'pending' => 'bg-slate-700 text-slate-200 ring-slate-600',
    'pending_ingest' => 'bg-yellow-500/10 text-yellow-200 ring-yellow-500/20',
    'not_started' => 'bg-slate-800 text-slate-400 ring-slate-700',
    'processing' => 'bg-purple-500/10 text-purple-300 ring-purple-500/20',
    'completed' => 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/20',
    'failed' => 'bg-red-500/10 text-red-300 ring-red-500/20',
    'ready' => 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/20',
];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset '.($map[$status] ?? $map['draft'])]) }}>{{ str($status)->replace('_', ' ')->headline() }}</span>
