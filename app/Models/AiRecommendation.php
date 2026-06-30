<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRecommendation extends Model
{
    protected $fillable = ['project_id', 'clip_id', 'type', 'title', 'content', 'confidence_score', 'raw_payload'];
    protected function casts(): array { return ['content' => 'array', 'raw_payload' => 'array', 'confidence_score' => 'decimal:2']; }
    public function project(): BelongsTo { return $this->belongsTo(VideoProject::class, 'project_id'); }
    public function clip(): BelongsTo { return $this->belongsTo(Clip::class); }
}
