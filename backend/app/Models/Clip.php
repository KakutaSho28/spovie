<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
        'download_token',
    ];

    protected $hidden = [
        'download_token',
    ];

    protected static function booted(): void
    {
        // ダウンロードURLを推測できないようにランダムトークンを付与する
        static::creating(function (Clip $clip) {
            $clip->download_token ??= Str::random(40);
        });
    }

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
