<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendReport extends Model
{
    protected $fillable = ['user_id', 'project_id', 'niche', 'topic', 'platform', 'region', 'score', 'hashtags', 'angles', 'hooks', 'captions', 'raw_payload', 'generated_at'];
    protected function casts(): array { return ['hashtags' => 'array', 'angles' => 'array', 'hooks' => 'array', 'captions' => 'array', 'raw_payload' => 'array', 'score' => 'decimal:2', 'generated_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function project(): BelongsTo { return $this->belongsTo(VideoProject::class, 'project_id'); }
}
