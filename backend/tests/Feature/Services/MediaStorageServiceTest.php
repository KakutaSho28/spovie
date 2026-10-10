<?php

namespace Tests\Feature\Services;

use App\Models\Video;
use App\Services\MediaStorageService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class MediaStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_disk_uses_plain_url(): void
    {
        config(['filesystems.default' => 'public', 'media.temporary_urls' => null]);
        $service = app(MediaStorageService::class);

        $this->assertFalse($service->usesTemporaryUrls());
        $this->assertTrue($service->isLocal());
        $this->assertStringEndsWith('/storage/videos/a.mp4', $service->playbackUrl('videos/a.mp4'));
    }

    public function test_s3_disk_uses_one_hour_temporary_url_by_default(): void
    {
        $this->useFakeS3Disk($disk);
        $disk->shouldReceive('temporaryUrl')
            ->once()
            ->withArgs(fn ($path, $expires) => $path === 'videos/a.mp4'
                && abs($expires->diffInMinutes(now(), true) - 60) <= 1)
            ->andReturn('https://bucket.example/signed');

        $service = app(MediaStorageService::class);

        $this->assertTrue($service->usesTemporaryUrls());
        $this->assertFalse($service->isLocal());
        $this->assertSame('https://bucket.example/signed', $service->playbackUrl('videos/a.mp4'));
    }

    public function test_temporary_urls_can_be_disabled_for_public_buckets(): void
    {
        $this->useFakeS3Disk($disk);
        config(['media.temporary_urls' => 'false']);
        $disk->shouldReceive('url')->once()->with('videos/a.mp4')->andReturn('https://cdn.example/videos/a.mp4');

        $this->assertSame('https://cdn.example/videos/a.mp4', app(MediaStorageService::class)->playbackUrl('videos/a.mp4'));
    }

    public function test_s3_download_redirects_to_signed_url_with_attachment_name(): void
    {
        $this->useFakeS3Disk($disk);
        $disk->shouldReceive('temporaryUrl')
            ->once()
            ->withArgs(fn ($path, $expires, $options) => $options['ResponseContentDisposition'] === 'attachment; filename="goal.mp4"')
            ->andReturn('https://bucket.example/clip-signed');

        $response = app(MediaStorageService::class)->download('clips/x.mp4', 'goal.mp4');

        $this->assertSame('https://bucket.example/clip-signed', $response->getTargetUrl());
    }

    public function test_video_resource_file_url_goes_through_storage_service(): void
    {
        $this->useFakeS3Disk($disk);
        $disk->shouldReceive('temporaryUrl')->andReturn('https://bucket.example/signed');
        $video = Video::factory()->upload()->make();

        $this->assertSame('https://bucket.example/signed', $video->fileUrl());
        $this->assertNull(Video::factory()->make()->fileUrl());
    }

    public function test_upload_limit_is_configurable_and_enforced(): void
    {
        config(['media.max_upload_mb' => 1]);
        Storage::fake('public');
        $user = \App\Models\User::factory()->create();
        $file = \Illuminate\Http\UploadedFile::fake()->create('big.mp4', 2048, 'video/mp4');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/videos/upload', ['title' => 't', 'file' => $file])
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', fn ($m) => str_contains($m, '1MB以下'));
    }

    private function useFakeS3Disk(?Filesystem &$disk): void
    {
        $disk = Mockery::mock(Filesystem::class);
        config([
            'filesystems.default' => 's3fake',
            'filesystems.disks.s3fake' => ['driver' => 's3'],
            'media.temporary_urls' => null,
            'media.url_ttl_minutes' => 60,
        ]);
        Storage::set('s3fake', $disk);
    }
}
