<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RenderClipRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('update', $this->route('clip')->project) ?? false; }

    public function rules(): array
    {
        return [
            'platform' => ['required', 'in:shorts,tiktok,reels'],
            'title' => ['nullable', 'string', 'max:120'],
            'caption' => ['nullable', 'string', 'max:2200'],
            'hashtags' => ['nullable', 'array'],
            'hashtags.*' => ['string', 'max:80'],
            'hook_text' => ['nullable', 'string', 'max:160'],
            'subtitle_segments' => ['nullable', 'array'],
            'subtitle_segments.*.start' => ['required_with:subtitle_segments', 'numeric', 'min:0'],
            'subtitle_segments.*.end' => ['required_with:subtitle_segments', 'numeric', 'min:0'],
            'subtitle_segments.*.text' => ['required_with:subtitle_segments', 'string', 'max:240'],
            'options' => ['nullable', 'array'],
            'options.crop_mode' => ['nullable', 'in:smart_crop,center_crop,fit_blur'],
        ];
    }
}
