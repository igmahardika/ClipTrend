@extends('layouts.app', ['pageTitle' => 'Admin Settings'])
@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}" class="ct-panel max-w-3xl p-6">@csrf @method('PUT')
    <h2 class="text-xl font-black text-white">Platform Settings</h2>
    <label class="mt-5 block text-sm text-slate-300">Max Upload MB<input name="max_upload_mb" type="number" value="{{ config('cliptrend.max_upload_mb') }}" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white"></label>
    <label class="mt-4 block text-sm text-slate-300">Render Timeout Seconds<input name="render_timeout" type="number" value="{{ config('cliptrend.render_timeout') }}" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white"></label>
    <label class="mt-4 block text-sm text-slate-300">Subtitle Templates JSON<textarea name="subtitle_templates" rows="5" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white">{{ json_encode(config('cliptrend.default_subtitle_style'), JSON_PRETTY_PRINT) }}</textarea></label>
    <label class="mt-4 block text-sm text-slate-300">Export Presets JSON<textarea name="export_presets" rows="5" class="mt-2 w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white">{"shorts":{"ratio":"9:16"},"tiktok":{"ratio":"9:16"},"reels":{"ratio":"9:16"}}</textarea></label>
    <button class="ct-button mt-6">Save Settings</button>
</form>
@endsection
