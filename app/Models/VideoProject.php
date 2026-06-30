<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

class VideoProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'title', 'slug', 'description', 'source_type', 'youtube_url',
        'status', 'target_platforms', 'niche_detection_status', 'render_status',
        'total_duration_seconds', 'metadata',
    ];

    protected function casts(): array
    {
        return ['target_platforms' => 'array', 'metadata' => 'array', 'total_duration_seconds' => 'float'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function uploadedVideo(): HasOne { return $this->hasOne(UploadedVideo::class, 'project_id')->latestOfMany(); }
    public function analyses(): HasMany { return $this->hasMany(VideoAnalysis::class, 'project_id'); }
    public function primaryAnalysis(): HasOne { return $this->hasOne(VideoAnalysis::class, 'project_id')->latestOfMany(); }
    public function detectedNiches(): HasMany { return $this->hasMany(DetectedNiche::class, 'project_id'); }
    public function clips(): HasMany { return $this->hasMany(Clip::class, 'project_id'); }
    public function renderJobs(): HasMany { return $this->hasMany(RenderJob::class, 'project_id'); }
    public function renderedVideos(): HasMany { return $this->hasMany(RenderedVideo::class, 'project_id'); }
    public function aiRecommendations(): HasMany { return $this->hasMany(AiRecommendation::class, 'project_id'); }
}
