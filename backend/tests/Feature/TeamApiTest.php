<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamApiTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $owner;
    private User $member;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create();
        $this->owner = $this->team->owner;
        $this->member = User::factory()->create();
        $this->outsider = User::factory()->create();
        $this->team->memberships()->create([
            'user_id' => $this->member->id,
            'role' => TeamMember::ROLE_MEMBER,
            'joined_at' => now(),
        ]);
    }

    // ---- 作成・一覧 ----

    public function test_creator_becomes_owner_and_first_member(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $id = $this->postJson('/api/teams', ['name' => 'FC Spovie'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'FC Spovie')
            ->assertJsonPath('data.owner.id', $user->id)
            ->assertJsonPath('data.members.0.role', 'owner')
            ->json('data.id');

        $this->getJson('/api/teams')->assertOk()->assertJsonPath('data.0.id', $id);
    }

    public function test_index_lists_only_teams_the_user_belongs_to(): void
    {
        Team::factory()->create();

        Sanctum::actingAs($this->member);
        $this->getJson('/api/teams')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->team->id);

        Sanctum::actingAs($this->outsider);
        $this->getJson('/api/teams')->assertOk()->assertJsonCount(0, 'data');
    }

    // ---- 詳細（メンバーのみ） ----

    public function test_show_is_members_only(): void
    {
        Sanctum::actingAs($this->owner);
        $this->getJson("/api/teams/{$this->team->id}")->assertOk()->assertJsonCount(2, 'data.members');

        Sanctum::actingAs($this->member);
        $this->getJson("/api/teams/{$this->team->id}")->assertOk();

        Sanctum::actingAs($this->outsider);
        $this->getJson("/api/teams/{$this->team->id}")->assertForbidden();
    }

    // ---- 削除（オーナーのみ） ----

    public function test_only_owner_can_delete_team_and_videos_become_personal(): void
    {
        $video = Video::factory()->for($this->member)->create(['team_id' => $this->team->id]);

        foreach ([$this->member, $this->outsider] as $user) {
            Sanctum::actingAs($user);
            $this->deleteJson("/api/teams/{$this->team->id}")->assertForbidden();
        }

        Sanctum::actingAs($this->owner);
        $this->deleteJson("/api/teams/{$this->team->id}")->assertOk();

        $this->assertDatabaseMissing('teams', ['id' => $this->team->id]);
        $this->assertNull($video->fresh()->team_id);
    }

    // ---- 参加（POST /teams/join、冪等） ----

    public function test_join_with_invite_token_is_idempotent(): void
    {
        Sanctum::actingAs($this->outsider);

        foreach ([1, 2] as $_) {
            $this->postJson('/api/teams/join', ['invite_token' => $this->team->invite_token])
                ->assertOk()
                ->assertJsonPath('data.id', $this->team->id);
        }

        $this->assertSame(1, $this->team->memberships()->where('user_id', $this->outsider->id)->count());
        $this->getJson("/api/teams/{$this->team->id}")->assertOk();
    }

    public function test_join_rejects_invalid_or_missing_token(): void
    {
        Sanctum::actingAs($this->outsider);

        $this->postJson('/api/teams/join', ['invite_token' => 'nope'])->assertNotFound();
        $this->postJson('/api/teams/join', [])->assertUnprocessable();
    }

    public function test_join_requires_authentication(): void
    {
        $this->postJson('/api/teams/join', ['invite_token' => $this->team->invite_token])->assertUnauthorized();
    }

    // ---- メンバー削除 / 脱退 ----

    public function test_owner_can_remove_a_member(): void
    {
        Sanctum::actingAs($this->owner);

        $this->deleteJson("/api/teams/{$this->team->id}/members/{$this->member->id}")
            ->assertOk()
            ->assertJsonPath('message', 'メンバーを削除しました');

        $this->assertFalse($this->team->hasMember($this->member));
    }

    public function test_member_can_remove_self_but_not_others(): void
    {
        $other = User::factory()->create();
        $this->team->memberships()->create(['user_id' => $other->id, 'role' => 'member', 'joined_at' => now()]);

        Sanctum::actingAs($this->member);
        $this->deleteJson("/api/teams/{$this->team->id}/members/{$other->id}")->assertForbidden();
        $this->deleteJson("/api/teams/{$this->team->id}/members/{$this->member->id}")
            ->assertOk()
            ->assertJsonPath('message', 'チームを脱退しました');

        $this->assertFalse($this->team->hasMember($this->member));
        $this->assertTrue($this->team->hasMember($other));
    }

    public function test_owner_cannot_leave_or_be_removed(): void
    {
        Sanctum::actingAs($this->owner);
        $this->deleteJson("/api/teams/{$this->team->id}/members/{$this->owner->id}")->assertUnprocessable();

        // メンバーがオーナーを外そうとしても 403
        Sanctum::actingAs($this->member);
        $this->deleteJson("/api/teams/{$this->team->id}/members/{$this->owner->id}")->assertForbidden();
    }

    public function test_outsider_cannot_remove_anyone(): void
    {
        Sanctum::actingAs($this->outsider);

        $this->deleteJson("/api/teams/{$this->team->id}/members/{$this->member->id}")->assertForbidden();
    }

    // ---- チーム動画（メンバーのみ） ----

    public function test_team_videos_are_members_only(): void
    {
        Video::factory()->for($this->owner)->count(2)->create(['team_id' => $this->team->id]);
        Video::factory()->for($this->owner)->create();

        foreach ([$this->owner, $this->member] as $user) {
            Sanctum::actingAs($user);
            $this->getJson("/api/teams/{$this->team->id}/videos")->assertOk()->assertJsonCount(2, 'data');
        }

        Sanctum::actingAs($this->outsider);
        $this->getJson("/api/teams/{$this->team->id}/videos")->assertForbidden();
    }

    // ---- 動画の追加先チーム ----

    public function test_video_can_only_be_added_to_own_team(): void
    {
        $payload = ['title' => '試合', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ'];

        Sanctum::actingAs($this->member);
        $this->postJson('/api/videos', $payload + ['team_id' => $this->team->id])
            ->assertCreated()
            ->assertJsonPath('data.team.id', $this->team->id);

        Sanctum::actingAs($this->outsider);
        $this->postJson('/api/videos', $payload + ['team_id' => $this->team->id])->assertForbidden();
    }
}
