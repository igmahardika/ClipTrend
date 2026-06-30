<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VideoProject;

class VideoProjectPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, VideoProject $project): bool { return $user->hasRole('admin') || $project->user_id === $user->id; }
    public function create(User $user): bool { return $user->status === 'active'; }
    public function update(User $user, VideoProject $project): bool { return $project->user_id === $user->id || $user->hasRole('admin'); }
    public function delete(User $user, VideoProject $project): bool { return $project->user_id === $user->id || $user->hasRole('admin'); }
}
