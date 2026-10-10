<?php

namespace Tests\Feature\Services;

use App\Exceptions\ServiceException;
use App\Jobs\ProcessClipJob;
use App\Models\Clip;
use App\Models\Video;
use App\Services\ClipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClipServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_dispatches_job_for_uploaded_video(): void
    {
        Queue::fake();
        $video = Video::factory()->upload()->create();

        $clip = app(ClipService::class)->create($video, null, 'ゴール', 1, 5);

        $this->assertSame(Clip::STATUS_PROCESSING, $clip->status);
        Queue::assertPushed(ProcessClipJob::class, fn ($job) => $job->clip->is($clip));
    }

    public function test_create_rejects_youtube_video_per_copyright_policy(): void
    {
        Queue::fake();
        $video = Video::factory()->create();

        $this->expectException(ServiceException::class);
        try {
            app(ClipService::class)->create($video, null, 'x', 1, 5);
        } finally {
            Queue::assertNothingPushed();
            $this->assertSame(0, Clip::count());
        }
    }

    public function test_download_name_requires_token_done_status_and_existing_file(): void
    {
        Storage::fake(config('filesystems.default'));
        $service = app(ClipService::class);
        $clip = Clip::factory()->done()->create(['title' => 'Nice Goal']);

        // ファイルがない
        $this->assertThrowsNotFound(fn () => $service->downloadName($clip, $clip->download_token));

        Storage::disk(config('filesystems.default'))->put('clips/test.mp4', 'x');
        $this->assertSame('nice_goal.mp4', $service->downloadName($clip, $clip->download_token));
        $this->assertThrowsNotFound(fn () => $service->downloadName($clip, 'wrong'));
    }

    private function assertThrowsNotFound(callable $fn): void
    {
        try {
            $fn();
            $this->fail('ServiceException expected');
        } catch (ServiceException $e) {
            $this->assertSame(404, $e->status);
        }
    }
}
