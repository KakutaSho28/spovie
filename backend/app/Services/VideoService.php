<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\User;
use App\Models\Video;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VideoService
{
    /**
     * 一覧取得。$teamId があればそのチームの動画のみ（所属チームでなければ 403）、
     * なければ scope=personal で個人動画のみ、scope=all（既定）で個人 + 所属チーム。
     */
    public function paginateVisibleTo(User $user, int $perPage = 20, string $scope = 'all', ?int $teamId = null): LengthAwarePaginator
    {
        $query = Video::query();

        if ($teamId !== null) {
            if (! $user->teams()->where('teams.id', $teamId)->exists()) {
                throw ServiceException::forbidden();
            }
            $query->where('team_id', $teamId);
        } elseif ($scope === 'personal') {
            $query->personalOf($user);
        } else {
            $query->visibleTo($user);
        }

        return $query
            ->with('team')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function createYoutube(User $user, string $title, string $youtubeUrl, ?int $teamId): Video
    {
        $this->assertCanUseTeam($user, $teamId);

        $video = $user->videos()->create([
            'team_id' => $teamId,
            'type' => Video::TYPE_YOUTUBE,
            'youtube_video_id' => $this->extractYoutubeVideoId($youtubeUrl),
            'title' => $title,
        ]);

        // レスポンスの team.name を一覧・詳細と揃えるため、チームを読み込んでおく
        return $video->load('team');
    }

    public function delete(Video $video): void
    {
        $video->delete();
    }

    /**
     * 指定チームの動画を追加できるか（所属チームのみ）
     */
    public function assertCanUseTeam(User $user, ?int $teamId): void
    {
        if ($teamId && ! $user->teams()->where('teams.id', $teamId)->exists()) {
            throw ServiceException::forbidden('このチームに動画を追加できません');
        }
    }

    /**
     * 対応形式: watch?v= / youtu.be/ / embed/
     */
    public function extractYoutubeVideoId(string $url): string
    {
        preg_match('/(?:v=|youtu\.be\/|embed\/)([a-zA-Z0-9_-]{11})/', $url, $matches);

        return $matches[1];
    }
}
