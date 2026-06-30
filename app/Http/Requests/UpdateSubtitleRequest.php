<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubtitleRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('update', $this->route('clip')->project) ?? false; }

    public function rules(): array
    {
        return [
            'segments' => ['required', 'array', 'min:1'],
            'segments.*.start' => ['required', 'numeric', 'min:0'],
            'segments.*.end' => ['required', 'numeric', 'min:0'],
            'segments.*.text' => ['required', 'string', 'max:240'],
            'style' => ['nullable', 'array'],
            'style.font' => ['nullable', 'string', 'max:80'],
            'style.size' => ['nullable', 'integer', 'min:12', 'max:96'],
            'style.position' => ['nullable', 'string', 'max:30'],
            'style.color' => ['nullable', 'string', 'max:30'],
            'style.highlight_color' => ['nullable', 'string', 'max:30'],
        ];
    }
}
