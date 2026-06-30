<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoAnalysis extends Model
{
    protected $fillable = [
        'project_id', 'uploaded_video_id', 'detected_niche_id', 'summary', 'main_topic',
        'audience_profile', 'content_style', 'recommended_output', 'recommended_duration_seconds',
        'ai_confidence', 'reasoning', 'raw_payload', 'analyzed_at'
    ];

    protected function casts(): array
    {
        return [
            'audience_profile' => 'array',
            'recommended_output' => 'array',
            'recommended_duration_seconds' => 'array',
            'raw_payload' => 'array',
            'ai_confidence' => 'decimal:2',
            'analyzed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo { return $this->belongsTo(VideoProject::class, 'project_id'); }
    public function uploadedVideo(): BelongsTo { return $this->belongsTo(UploadedVideo::class, 'uploaded_video_id'); }
    public function primaryNiche(): BelongsTo { return $this->belongsTo(DetectedNiche::class, 'detected_niche_id'); }
    public function detectedNiche(): BelongsTo { return $this->belongsTo(DetectedNiche::class, 'detected_niche_id'); }
}
