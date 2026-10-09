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

    public function test_join_is_idempotent_and_checks_token(): void
    {
        $service = app(TeamService::class);
        $team = Team::factory()->create();
        $user = User::factory()->create();

        $service->join($team, $user, $team->invite_token);
        $service->join($team, $user, $team->invite_token);
        $this->assertSame(1, $team->memberships()->where('user_id', $user->id)->count());

        $this->expectException(ServiceException::class);
        $service->join($team, User::factory()->create(), 'wrong');
    }

    public function test_owner_cannot_leave_or_remove_self_but_member_can_leave(): void
    {
        $service = app(TeamService::class);
        $team = Team::factory()->create();
        $member = User::factory()->create();
        $service->join($team, $member, $team->invite_token);

        $service->leave($team, $member);
        $this->assertFalse($team->hasMember($member));

        foreach ([
            fn () => $service->leave($team, $team->owner),
            fn () => $service->removeMember($team, $team->owner, $team->owner),
        ] as $fn) {
            try {
                $fn();
                $this->fail('ServiceException expected');
            } catch (ServiceException $e) {
                $this->assertSame(422, $e->status);
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
