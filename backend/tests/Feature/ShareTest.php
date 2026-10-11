<?php

namespace Tests\Feature;

use App\Models\Annotation;
use App\Models\ShareLink;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_share_link_for_youtube_video(): void
    {
        $video = Video::factory()->create();
        $link = ShareLink::factory()->for(Annotation::factory()->for($video))->create();

        $this->getJson("/api/share/{$link->token}")
            ->assertOk()
            ->assertJsonPath('data.video.type', 'youtube')
            ->assertJsonPath('data.video.youtube_video_id', $video->youtube_video_id)
            ->assertJsonPath('data.video.file_url', null);
    }

    public function test_share_link_for_uploaded_video_includes_file_url(): void
    {
        $video = Video::factory()->upload()->create();
        $link = ShareLink::factory()->for(Annotation::factory()->for($video))->create();

        $response = $this->getJson("/api/share/{$link->token}")
            ->assertOk()
            ->assertJsonPath('data.video.type', 'upload')
            ->assertJsonPath('data.video.youtube_video_id', null);

        $this->assertStringEndsWith($video->file_path, $response->json('data.video.file_url'));
    }

    public function test_share_url_uses_frontend_url(): void
    {
        config(['app.frontend_url' => 'https://spovie.example.com']);
        $video = Video::factory()->create();
        $annotation = Annotation::factory()->for($video)->create();

        $this->actingAs($video->user, 'sanctum')
            ->postJson("/api/annotations/{$annotation->id}/share", [])
            ->assertCreated()
            ->assertJsonPath('data.share_url', fn (string $url) => str_starts_with($url, 'https://spovie.example.com/share/'));
    }

    public function test_expired_and_missing_links(): void
    {
        $expired = ShareLink::factory()->create(['expires_at' => now()->subMinute()]);

        $this->getJson("/api/share/{$expired->token}")->assertStatus(410);
        $this->getJson('/api/share/unknown')->assertNotFound();
    }
}
