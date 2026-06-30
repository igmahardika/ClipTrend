<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

class Clip extends Model
{
    protected $fillable = [
        'project_id', 'uploaded_video_id', 'title', 'start_time', 'end_time', 'duration_seconds',
        'hook_text', 'transcript_excerpt', 'retention_score', 'viral_score', 'platform_fit', 'status'
    ];

    protected function casts(): array
    {
        return ['start_time' => 'float', 'end_time' => 'float', 'duration_seconds' => 'float', 'retention_score' => 'decimal:2', 'viral_score' => 'decimal:2', 'platform_fit' => 'array'];
    }

    public function project(): BelongsTo { return $this->belongsTo(VideoProject::class, 'project_id'); }
    public function uploadedVideo(): BelongsTo { return $this->belongsTo(UploadedVideo::class, 'uploaded_video_id'); }
    public function subtitle(): HasOne { return $this->hasOne(Subtitle::class); }
    public function renderJobs(): HasMany { return $this->hasMany(RenderJob::class); }
    public function renderedVideos(): HasMany { return $this->hasMany(RenderedVideo::class); }
}
