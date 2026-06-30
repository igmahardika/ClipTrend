<?php

namespace App\Http\Requests;

use App\Models\VideoProject;
use Illuminate\Foundation\Http\FormRequest;

class StoreVideoProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', VideoProject::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'source_type' => ['required', 'in:upload,youtube'],
            'youtube_url' => ['nullable', 'required_if:source_type,youtube', 'url', 'max:2048'],
            'target_platforms' => ['nullable', 'array'],
            'target_platforms.*' => ['in:shorts,tiktok,reels'],
        ];
    }
}
