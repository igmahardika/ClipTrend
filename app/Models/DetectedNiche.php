<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetectedNiche extends Model
{
    protected $fillable = ['project_id', 'name', 'slug', 'confidence_score', 'signals', 'reasoning', 'is_primary'];
    protected function casts(): array { return ['signals' => 'array', 'confidence_score' => 'decimal:2', 'is_primary' => 'boolean']; }
    public function project(): BelongsTo { return $this->belongsTo(VideoProject::class, 'project_id'); }
}
