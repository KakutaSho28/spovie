<?php

namespace App\Policies;

use App\Models\Clip;
use App\Models\User;

class ClipPolicy
{
    public function view(User $user, Clip $clip): bool
    {
        return $clip->video->canBeAccessedBy($user);
    }
}
