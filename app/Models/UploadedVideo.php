<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadedVideo extends Model
{
    protected $fillable = [
        'project_id', 'disk', 'path', 'original_filename', 'mime_type', 'extension',
        'size_bytes', 'duration_seconds', 'width', 'height', 'frame_rate', 'bitrate',
        'checksum', 'status', 'metadata'
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'duration_seconds' => 'float', 'size_bytes' => 'integer', 'width' => 'integer', 'height' => 'integer', 'bitrate' => 'integer'];
    }

    public function project(): BelongsTo { return $this->belongsTo(VideoProject::class, 'project_id'); }

    public function isReadyForAnalysis(): bool
    {
        return ! in_array($this->status, ['pending_ingest'], true)
            && ! str_ends_with((string) $this->path, '.pending');
    }
}
