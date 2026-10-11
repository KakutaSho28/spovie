<?php

namespace Tests\Feature;

use App\Models\Clip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClipDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));
        Storage::disk(config('filesystems.default'))->put('clips/test.mp4', 'fake-mp4');
    }

    public function test_clip_gets_random_download_token(): void
    {
        $clip = Clip::factory()->create();

        $this->assertSame(40, strlen($clip->download_token));
        $this->assertNotSame($clip->download_token, Clip::factory()->create()->download_token);
    }

    public function test_download_with_valid_token(): void
    {
        $clip = Clip::factory()->done()->create();

        $this->get("/api/clips/{$clip->id}/download/{$clip->download_token}")
            ->assertOk()
            ->assertDownload();
    }

    public function test_download_with_wrong_or_missing_token_is_404(): void
    {
        $clip = Clip::factory()->done()->create();

        $this->getJson("/api/clips/{$clip->id}/download/" . str_repeat('x', 40))->assertNotFound();
        $this->getJson("/api/clips/{$clip->id}/download")->assertNotFound();
    }

    public function test_download_of_unfinished_clip_is_404(): void
    {
        $clip = Clip::factory()->create();

        $this->getJson("/api/clips/{$clip->id}/download/{$clip->download_token}")->assertNotFound();
    }

    public function test_resource_exposes_tokenized_download_url_only_when_done(): void
    {
        $processing = Clip::factory()->create();
        $done = Clip::factory()->done()->create();

        Sanctum::actingAs($processing->video->user);
        $this->getJson("/api/clips/{$processing->id}")
            ->assertOk()
            ->assertJsonPath('data.download_url', null)
            ->assertJsonMissingPath('data.download_token');

        Sanctum::actingAs($done->video->user);
        $this->getJson("/api/clips/{$done->id}")
            ->assertOk()
            ->assertJsonPath('data.download_url', url("/api/clips/{$done->id}/download/{$done->download_token}"));
    }
}
