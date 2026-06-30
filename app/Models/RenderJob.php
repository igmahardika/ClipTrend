<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasOne};

class RenderJob extends Model
{
    protected $fillable = [
        'project_id', 'clip_id', 'user_id', 'status', 'progress', 'platform', 'preset',
        'error_message', 'attempts', 'started_at', 'completed_at'
    ];

    protected function casts(): array { return ['preset' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'progress' => 'integer']; }

    public function project(): BelongsTo { return $this->belongsTo(VideoProject::class, 'project_id'); }
    public function clip(): BelongsTo { return $this->belongsTo(Clip::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function renderedVideo(): HasOne { return $this->hasOne(RenderedVideo::class); }
}
