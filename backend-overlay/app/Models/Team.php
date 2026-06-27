<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'owner_id',
        'invite_token',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot('role', 'joined_at');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function hasMember(User $user): bool
    {
        return $this->memberships()->where('user_id', $user->id)->exists();
    }
}
