<?php

namespace Tests\Feature\Services;

use App\Exceptions\ServiceException;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_registers_owner_as_first_member(): void
    {
        $owner = User::factory()->create();

        $team = app(TeamService::class)->create($owner, 'FC Spovie');

        $this->assertSame(64, strlen($team->invite_token));
        $this->assertSame('owner', $team->members()->first()->pivot->role);
    }

    public function test_join_by_invite_token_is_idempotent(): void
    {
        $service = app(TeamService::class);
        $team = Team::factory()->create();
        $user = User::factory()->create();

        $service->joinByInviteToken($user, $team->invite_token);
        $joined = $service->joinByInviteToken($user, $team->invite_token);

        $this->assertTrue($joined->is($team));
        $this->assertSame(1, $team->memberships()->where('user_id', $user->id)->count());
        $this->assertSame('member', $team->memberships()->where('user_id', $user->id)->first()->role);
    }

    public function test_join_with_unknown_token_is_404(): void
    {
        try {
            app(TeamService::class)->joinByInviteToken(User::factory()->create(), 'unknown');
            $this->fail('ServiceException expected');
        } catch (ServiceException $e) {
            $this->assertSame(404, $e->status);
        }
    }

    public function test_remove_member_detaches_member_but_never_the_owner(): void
    {
        $service = app(TeamService::class);
        $team = Team::factory()->create();
        $member = User::factory()->create();
        $service->joinByInviteToken($member, $team->invite_token);

        $service->removeMember($team, $member);
        $this->assertFalse($team->hasMember($member));

        foreach ([
            [fn () => $service->removeMember($team, $team->owner), 422],
            [fn () => $service->removeMember($team, $member), 404],
        ] as [$fn, $status]) {
            try {
                $fn();
                $this->fail('ServiceException expected');
            } catch (ServiceException $e) {
                $this->assertSame($status, $e->status);
            }
        }
    }

    public function test_paginate_videos_returns_only_team_videos(): void
    {
        $team = Team::factory()->create();
        \App\Models\Video::factory()->count(2)->create(['team_id' => $team->id]);
        \App\Models\Video::factory()->create();

        $this->assertSame(2, app(TeamService::class)->paginateVideos($team)->total());
    }
}
