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

    /** メンバー削除: オーナーは誰でも、メンバーは自分自身のみ（脱退） */
    public function removeMember(User $actor, Team $team, User $target): bool
    {
        return $team->isOwner($actor) || $actor->id === $target->id;
    }

    /** チーム削除: オーナーのみ */
    public function manage(User $user, Team $team): bool
    {
        return $team->isOwner($user);
    }
}
