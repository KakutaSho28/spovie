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
     * 招待トークンで参加する（既にメンバーなら何もしない）
     */
    public function join(Team $team, User $user, string $inviteToken): Team
    {
        if (! hash_equals($team->invite_token, $inviteToken)) {
            throw new ServiceException('招待トークンが正しくありません');
        }

        if (! $team->hasMember($user)) {
            $team->members()->attach($user->id, [
                'role' => TeamMember::ROLE_MEMBER,
                'joined_at' => now(),
            ]);
        }

        return $team;
    }

    public function removeMember(Team $team, User $actor, User $target): void
    {
        if ($target->id === $actor->id) {
            throw new ServiceException('オーナー自身は削除できません');
        }

        $team->members()->detach($target->id);
    }

    public function leave(Team $team, User $user): void
    {
        if ($team->isOwner($user)) {
            throw new ServiceException('オーナーはチームを脱退できません');
        }

        $team->members()->detach($user->id);
    }

    public function delete(Team $team): void
    {
        $team->delete();
    }

    public function paginateVideos(Team $team, int $perPage = 20): LengthAwarePaginator
    {
        return $team->videos()->with('team')->orderByDesc('created_at')->paginate($perPage);
    }
}
