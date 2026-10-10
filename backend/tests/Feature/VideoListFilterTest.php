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

    public function test_list_is_paginated_20_per_page_with_page_meta(): void
    {
        // setUp の2件（個人1 + チーム1）に加えて 43 件 → 合計 45 件
        Video::factory()->count(43)->for($this->me)->create();

        $page1 = $this->getJson('/api/videos')->assertOk();
        $page1->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 45);

        $this->getJson('/api/videos?page=3')->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('meta.current_page', 3);

        // ページをまたいでも重複・欠落がない（作成日時の新しい順）
        $all = collect([1, 2, 3])->flatMap(fn ($page) => collect($this->getJson("/api/videos?page={$page}")->json('data'))->pluck('id'));
        $this->assertCount(45, $all->unique());
    }

    public function test_page_beyond_the_last_returns_empty_data_with_last_page_meta(): void
    {
        $this->getJson('/api/videos?page=9')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.last_page', 1);
    }

    public function test_pagination_respects_filters(): void
    {
        Video::factory()->count(25)->for($this->me)->create();

        $this->getJson('/api/videos?scope=personal')
            ->assertJsonPath('meta.total', 26)
            ->assertJsonPath('meta.last_page', 2);
        $this->getJson("/api/videos?team_id={$this->team->id}")->assertJsonPath('meta.total', 1);
    }

    public function test_invalid_page_is_rejected(): void
    {
        $this->getJson('/api/videos?page=0')->assertUnprocessable();
        $this->getJson('/api/videos?page=abc')->assertUnprocessable();
    }

    public function test_invalid_scope_is_rejected(): void
    {
        $this->getJson('/api/videos?scope=everything')->assertUnprocessable();
    }
}
