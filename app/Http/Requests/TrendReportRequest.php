<?php

namespace App\Http\Requests;

use App\Models\VideoProject;
use Illuminate\Foundation\Http\FormRequest;

class TrendReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $projectId = $this->input('project_id');
        if (! $projectId) {
            return true;
        }

        $project = VideoProject::find($projectId);
        return $project ? $user->can('view', $project) : false;
    }

    public function rules(): array
    {
        return [
            'niche' => ['nullable', 'string', 'max:120'],
            'topic' => ['nullable', 'string', 'max:160'],
            'platform' => ['required', 'in:shorts,tiktok,reels'],
            'region' => ['required', 'string', 'max:10'],
            'project_id' => ['nullable', 'integer', 'exists:video_projects,id'],
        ];
    }
}
