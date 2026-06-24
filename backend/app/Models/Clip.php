<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Clip extends Model
{
    use HasFactory;

    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'video_id',
        'annotation_id',
        'title',
        'start_seconds',
        'end_seconds',
        'file_path',
        'status',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function annotation(): BelongsTo
    {
        return $this->belongsTo(Annotation::class);
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }
}
