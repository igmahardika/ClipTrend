<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RenderedVideo extends Model
{
    protected $fillable = [
        'project_id', 'clip_id', 'render_job_id', 'user_id', 'platform', 'disk', 'path', 'thumbnail_path',
        'size_bytes', 'duration_seconds', 'width', 'height', 'title', 'caption', 'hashtags', 'status', 'downloaded_at'
    ];

    protected function casts(): array { return ['hashtags' => 'array', 'downloaded_at' => 'datetime', 'duration_seconds' => 'float']; }

    public function project(): BelongsTo { return $this->belongsTo(VideoProject::class, 'project_id'); }
    public function clip(): BelongsTo { return $this->belongsTo(Clip::class); }
    public function renderJob(): BelongsTo { return $this->belongsTo(RenderJob::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
