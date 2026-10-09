<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /** 詳細・チーム動画一覧: メンバーのみ */
    public function view(User $user, Team $team): bool
    {
        return $team->hasMember($user);
    }

    /** 削除・メンバー削除: オーナーのみ */
    public function manage(User $user, Team $team): bool
    {
        return $team->isOwner($user);
    }
}
