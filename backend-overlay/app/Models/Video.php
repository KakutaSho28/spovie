<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Video extends Model
{
    use HasFactory;

    public const TYPE_YOUTUBE = 'youtube';
    public const TYPE_UPLOAD = 'upload';

    protected $fillable = [
        'user_id',
        'team_id',
        'type',
        'youtube_video_id',
        'file_path',
        'title',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function annotations(): HasMany
    {
        return $this->hasMany(Annotation::class);
    }

    public function clips(): HasMany
    {
        return $this->hasMany(Clip::class);
    }

    public function isUpload(): bool
    {
        return $this->type === self::TYPE_UPLOAD;
    }

    public function canBeAccessedBy(User $user): bool
    {
        if ($this->team_id) {
            return $this->team()->whereHas('memberships', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->exists();
        }

        return $this->user_id === $user->id;
    }
}
