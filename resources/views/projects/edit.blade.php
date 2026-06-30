@extends('layouts.app', ['pageTitle' => 'Edit Project'])
@section('content')
<form method="POST" action="{{ route('projects.update', $project) }}" class="ct-panel max-w-3xl p-6">@csrf @method('PUT')
    <label class="block text-sm text-slate-300">Judul<input name="title" value="{{ $project->title }}" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white"></label>
    <label class="mt-4 block text-sm text-slate-300">Deskripsi<textarea name="description" rows="4" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white">{{ $project->description }}</textarea></label>
    <input type="hidden" name="source_type" value="{{ $project->source_type }}"><input type="hidden" name="youtube_url" value="{{ $project->youtube_url }}">
    <button class="ct-button mt-6">Save</button>
</form>
@endsection
