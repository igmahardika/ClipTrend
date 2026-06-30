<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadVideoRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('update', $this->route('project')) ?? false; }

    public function rules(): array
    {
        $globalMaxMb = (int) config('cliptrend.max_upload_mb', 1024);
        $userMaxMb = (int) ($this->user()?->upload_limit_mb ?: $globalMaxMb);
        $maxMb = min($globalMaxMb, max(1, $userMaxMb));

        return [
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/x-matroska,video/webm,video/x-msvideo,video/x-ms-wmv,video/mpeg,video/3gpp,application/octet-stream', 'max:'.($maxMb * 1024)],
        ];
    }
}
