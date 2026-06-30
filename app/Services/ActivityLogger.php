<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\VideoProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public function log(string $action, ?Model $subject = null, array $context = [], ?string $description = null): ActivityLog
    {
        $projectId = null;

        if ($subject instanceof VideoProject) {
            $projectId = $subject->id;
        } elseif ($subject instanceof Model && isset($subject->project_id)) {
            $projectId = $subject->project_id;
        }

        $request = request();

        return ActivityLog::create([
            'user_id' => Auth::id(),
            'project_id' => $projectId,
            'action' => $action,
            'description' => $description,
            'context' => $context,
            'ip_address' => app()->runningInConsole() ? null : $request->ip(),
            'user_agent' => app()->runningInConsole() ? null : $request->userAgent(),
        ]);
    }
}
