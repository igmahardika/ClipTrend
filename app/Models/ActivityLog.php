<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'project_id', 'action', 'description', 'context', 'ip_address', 'user_agent'];
    protected function casts(): array { return ['context' => 'array']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function project(): BelongsTo { return $this->belongsTo(VideoProject::class, 'project_id'); }
}
