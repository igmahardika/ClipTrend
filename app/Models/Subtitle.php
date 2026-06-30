<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subtitle extends Model
{
    protected $fillable = ['project_id', 'clip_id', 'language', 'segments', 'style', 'status'];
    protected function casts(): array { return ['segments' => 'array', 'style' => 'array']; }
    public function project(): BelongsTo { return $this->belongsTo(VideoProject::class, 'project_id'); }
    public function clip(): BelongsTo { return $this->belongsTo(Clip::class); }
}
