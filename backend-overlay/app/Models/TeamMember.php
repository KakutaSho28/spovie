<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMember extends Model
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_MEMBER = 'member';

    public $timestamps = false;

    protected $fillable = [
        'team_id',
        'user_id',
        'role',
        'joined_at',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
