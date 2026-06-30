<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSettingsController extends Controller
{
    public function edit(): View
    {
        $settings = Setting::orderBy('group')->orderBy('key')->get();
        return view('admin.settings.edit', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'max_upload_mb' => ['required', 'integer', 'min:100', 'max:51200'],
            'render_timeout' => ['required', 'integer', 'min:300', 'max:14400'],
            'subtitle_templates' => ['nullable', 'string', 'max:10000'],
            'export_presets' => ['nullable', 'string', 'max:10000'],
        ]);

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(
                ['group' => 'admin', 'key' => $key],
                ['value' => $value, 'type' => is_numeric($value) ? 'integer' : 'json']
            );
        }

        return back()->with('success', 'Settings berhasil disimpan.');
    }
}
