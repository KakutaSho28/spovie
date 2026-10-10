<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeamService
{
    /**
     * @return Collection<int, Team>
     */
    public function listFor(User $user): Collection
    {
        return $user->teams()->with(['owner', 'members'])->orderBy('teams.name')->get();
    }

    public function create(User $owner, string $name): Team
    {
        return DB::transaction(function () use ($owner, $name) {
            $team = Team::create([
                'name' => $name,
                'owner_id' => $owner->id,
                'invite_token' => Str::random(64),
            ]);

            $team->members()->attach($owner->id, [
                'role' => TeamMember::ROLE_OWNER,
                'joined_at' => now(),
            ]);

            return $team;
        });
    }

    public function findByInviteToken(string $token): Team
    {
        return Team::where('invite_token', $token)->firstOrFail();
    }

    /**
     * 招待トークンで参加する（冪等: 既にメンバーなら何もしない）
     */
    public function joinByInviteToken(User $user, string $inviteToken): Team
    {
        $team = Team::where('invite_token', $inviteToken)->first();

        if (! $team) {
            throw ServiceException::notFound('招待リンクが無効です');
        }

        if (! $team->hasMember($user)) {
            $team->members()->attach($user->id, [
                'role' => TeamMember::ROLE_MEMBER,
                'joined_at' => now(),
            ]);
        }

        return $team;
    }

    /**
     * メンバーを外す / 自分が脱退する（権限は TeamPolicy::removeMember で判定済みの前提）。
     * オーナーは外せない・脱退できない。
     */
    public function removeMember(Team $team, User $target): void
    {
        if ($team->isOwner($target)) {
            throw new ServiceException('オーナーは削除・脱退できません');
        }

        if (! $team->hasMember($target)) {
            throw ServiceException::notFound('このチームのメンバーではありません');
        }

        $team->members()->detach($target->id);
    }

    /**
     * チームを削除する。所属していた動画は投稿者の個人動画に戻す
     * （DB の ON DELETE SET NULL に加え、外部キーが効かない環境でも同じ結果にする）。
     */
    public function delete(Team $team): void
    {
        DB::transaction(function () use ($team) {
            $team->videos()->update(['team_id' => null]);
            $team->delete();
        });
    }

    public function paginateVideos(Team $team, int $perPage = 20): LengthAwarePaginator
    {
        return $team->videos()->with('team')->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);
    }
}
