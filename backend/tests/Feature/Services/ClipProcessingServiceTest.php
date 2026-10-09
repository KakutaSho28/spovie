<?php

namespace Tests\Feature\Services;

use App\Models\Clip;
use App\Services\ClipProcessingService;
use App\Support\Ffmpeg;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ClipProcessingServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $seenTempPaths = [];

    private function fakeFfmpeg(bool $succeed): void
    {
        $this->seenTempPaths = [];
        $test = $this;

        $this->app->instance(Ffmpeg::class, new class($succeed, $test) extends Ffmpeg {
            public function __construct(private bool $succeed, private ClipProcessingServiceTest $test) {}

            public function trim(string $input, string $output, int $startSeconds, int $endSeconds, int $timeout = 600): void
            {
                $this->test->record($input, $output);
                if (! $this->succeed) {
                    throw new RuntimeException('boom');
                }
                copy($input, $output);
            }
        });
    }

    public function record(string $input, string $output): void
    {
        $this->seenTempPaths = [$input, $output];
    }

    public function test_local_disk_processes_in_place(): void
    {
        Storage::fake('public');
        config(['filesystems.default' => 'public']);
        Storage::disk('public')->put('videos/src.mp4', 'video-bytes');
        $clip = Clip::factory()->create(['video_id' => \App\Models\Video::factory()->upload()->create(['file_path' => 'videos/src.mp4'])->id]);
        $this->fakeFfmpeg(true);

        app(ClipProcessingService::class)->process($clip);

        $clip->refresh();
        $this->assertSame(Clip::STATUS_DONE, $clip->status);
        $this->assertStringStartsWith('clips/', $clip->file_path);
        Storage::disk('public')->assertExists($clip->file_path);
    }

    public function test_remote_disk_downloads_to_temp_uploads_result_and_cleans_up(): void
    {
        $this->useRemoteDisk();
        Storage::disk('remote')->put('videos/src.mp4', 'video-bytes');
        $clip = Clip::factory()->create(['video_id' => \App\Models\Video::factory()->upload()->create(['file_path' => 'videos/src.mp4'])->id]);
        $this->fakeFfmpeg(true);

        app(ClipProcessingService::class)->process($clip);

        $clip->refresh();
        $this->assertSame(Clip::STATUS_DONE, $clip->status);
        $this->assertSame('video-bytes', Storage::disk('remote')->get($clip->file_path));
        foreach ($this->seenTempPaths as $path) {
            $this->assertFileDoesNotExist($path);
        }
    }

    public function test_failure_marks_clip_error_and_still_removes_temp_files(): void
    {
        $this->useRemoteDisk();
        Storage::disk('remote')->put('videos/src.mp4', 'video-bytes');
        $clip = Clip::factory()->create(['video_id' => \App\Models\Video::factory()->upload()->create(['file_path' => 'videos/src.mp4'])->id]);
        $this->fakeFfmpeg(false);

        app(ClipProcessingService::class)->process($clip);

        $this->assertSame(Clip::STATUS_ERROR, $clip->fresh()->status);
        $this->assertNotEmpty($this->seenTempPaths);
        foreach ($this->seenTempPaths as $path) {
            $this->assertFileDoesNotExist($path);
        }
    }

    public function test_missing_source_marks_clip_error(): void
    {
        $this->useRemoteDisk();
        $clip = Clip::factory()->create(['video_id' => \App\Models\Video::factory()->upload()->create(['file_path' => 'videos/none.mp4'])->id]);
        $this->fakeFfmpeg(true);

        app(ClipProcessingService::class)->process($clip);

        $this->assertSame(Clip::STATUS_ERROR, $clip->fresh()->status);
    }

    /** ローカルドライバではなく「リモート扱い」のディスクを作る（isLocal=false） */
    private function useRemoteDisk(): void
    {
        Storage::fake('remote');
        config([
            'filesystems.default' => 'remote',
            'filesystems.disks.remote.driver' => 's3',
        ]);
    }
}
