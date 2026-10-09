<?php

namespace Tests\Feature\Services;

use App\Exceptions\ServiceException;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Video;
use App\Services\VideoService;
use App\Services\VideoUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_to_scope_returns_own_personal_and_team_videos_only(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $myTeam = Team::factory()->create();
        $myTeam->memberships()->create(['user_id' => $me->id, 'role' => TeamMember::ROLE_MEMBER, 'joined_at' => now()]);
        $otherTeam = Team::factory()->create();

        $mine = Video::factory()->for($me)->create();
        $inMyTeam = Video::factory()->for($myTeam->owner)->create(['team_id' => $myTeam->id]);
        Video::factory()->for($other)->create();
        Video::factory()->for($otherTeam->owner)->create(['team_id' => $otherTeam->id]);
        // 自分が投稿しても、所属していないチームの動画は見えない
        Video::factory()->for($me)->create(['team_id' => $otherTeam->id]);

        $ids = Video::visibleTo($me)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$mine->id, $inMyTeam->id], $ids);
    }

    public function test_create_youtube_extracts_id_from_each_url_format(): void
    {
        $user = User::factory()->create();
        $service = app(VideoService::class);

        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ',
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
        ] as $url) {
            $video = $service->createYoutube($user, '試合', $url, null);
            $this->assertSame('dQw4w9WgXcQ', $video->youtube_video_id);
            $this->assertSame('youtube', $video->type);
        }
    }

    public function test_create_in_foreign_team_is_forbidden(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        try {
            app(VideoService::class)->createYoutube($user, 't', 'https://youtu.be/dQw4w9WgXcQ', $team->id);
            $this->fail('ServiceException expected');
        } catch (ServiceException $e) {
            $this->assertSame(403, $e->status);
        }
    }

    public function test_upload_stores_file_on_default_disk(): void
    {
        Storage::fake(config('filesystems.default'));
        $user = User::factory()->create();

        $video = app(VideoUploadService::class)->store($user, '練習', UploadedFile::fake()->create('a.mp4', 10, 'video/mp4'), null);

        $this->assertSame('upload', $video->type);
        Storage::disk(config('filesystems.default'))->assertExists($video->file_path);
    }
}
