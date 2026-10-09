<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VideoListFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $me;
    private Team $team;
    private Video $personal;
    private Video $teamVideo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = User::factory()->create();
        $this->team = Team::factory()->create();
        $this->team->memberships()->create(['user_id' => $this->me->id, 'role' => TeamMember::ROLE_MEMBER, 'joined_at' => now()]);

        $this->personal = Video::factory()->for($this->me)->create();
        $this->teamVideo = Video::factory()->for($this->team->owner)->create(['team_id' => $this->team->id]);
        Video::factory()->create(); // 他人の個人動画
        Video::factory()->for(Team::factory()->create()->owner)->create(['team_id' => Team::factory()->create()->id]); // 非所属チーム

        Sanctum::actingAs($this->me);
    }

    private function ids(string $query = ''): array
    {
        return collect($this->getJson('/api/videos' . $query)->assertOk()->json('data'))->pluck('id')->all();
    }

    public function test_default_and_scope_all_return_personal_plus_team_videos(): void
    {
        $expected = [$this->personal->id, $this->teamVideo->id];

        $this->assertEqualsCanonicalizing($expected, $this->ids());
        $this->assertEqualsCanonicalizing($expected, $this->ids('?scope=all'));
    }

    public function test_scope_personal_returns_only_own_personal_videos(): void
    {
        $this->assertSame([$this->personal->id], $this->ids('?scope=personal'));
    }

    public function test_team_id_filters_to_one_team(): void
    {
        $this->assertSame([$this->teamVideo->id], $this->ids("?team_id={$this->team->id}"));
    }

    public function test_team_id_of_foreign_team_is_forbidden(): void
    {
        $foreign = Team::factory()->create();

        $this->getJson("/api/videos?team_id={$foreign->id}")->assertForbidden();
    }

    public function test_invalid_scope_is_rejected(): void
    {
        $this->getJson('/api/videos?scope=everything')->assertUnprocessable();
    }
}
