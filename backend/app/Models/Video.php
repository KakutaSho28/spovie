<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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

    /**
     * ユーザーが閲覧できる動画: 自分の個人動画 + 所属チームの動画
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where(fn (Builder $personal) => $personal->where('user_id', $user->id)->whereNull('team_id'))
                ->orWhereIn('team_id', $user->teams()->select('teams.id'));
        });
    }

    public function isUpload(): bool
    {
        return $this->type === self::TYPE_UPLOAD;
    }

    /**
     * アップロード動画の再生URL（YouTube動画は null）
     */
    public function fileUrl(): ?string
    {
        if (! $this->isUpload() || ! $this->file_path) {
            return null;
        }

        return Storage::disk(config('filesystems.default'))->url($this->file_path);
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
