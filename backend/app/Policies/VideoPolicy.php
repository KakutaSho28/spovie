<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Video;

class VideoPolicy
{
    /**
     * 閲覧・アノテーション・切り抜き: 投稿者本人、またはチーム動画ならチームメンバー
     */
    public function view(User $user, Video $video): bool
    {
        return $video->canBeAccessedBy($user);
    }

    /**
     * 削除: 投稿者本人、またはチーム動画ならチームオーナー
     */
    public function delete(User $user, Video $video): bool
    {
        if ($video->user_id === $user->id) {
            return true;
        }

        return $video->team_id !== null && $video->team?->owner_id === $user->id;
    }
}
