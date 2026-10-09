<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VideoShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_beyond_first_page_can_be_fetched_directly(): void
    {
        $user = User::factory()->create();
        $first = Video::factory()->for($user)->create(['created_at' => now()->subDay()]);
        Video::factory()->count(25)->for($user)->create();

        Sanctum::actingAs($user);

        // 一覧（20件/ページ）の1ページ目には含まれない
        $ids = collect($this->getJson('/api/videos')->json('data'))->pluck('id');
        $this->assertNotContains($first->id, $ids);

        $this->getJson("/api/videos/{$first->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $first->id)
            ->assertJsonPath('data.type', 'youtube')
            ->assertJsonPath('data.youtube_video_id', $first->youtube_video_id);
    }

    public function test_missing_video_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/videos/999')->assertNotFound();
    }
}
