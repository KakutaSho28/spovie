<?php

namespace Tests\Feature;

use App\Models\Annotation;
use App\Models\Clip;
use App\Models\Comment;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $outsider;
    private Video $video;
    private Annotation $annotation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->outsider = User::factory()->create();
        $this->video = Video::factory()->upload()->for($this->owner)->create();
        $this->annotation = Annotation::factory()->for($this->video)->create();
    }

    /**
     * 他人の個人動画に関わる操作はすべて 403（共通メッセージ）になる
     *
     * @return array<string, array{string, string}>
     */
    public static function protectedEndpoints(): array
    {
        return [
            'video show' => ['GET', '/api/videos/{video}'],
            'video delete' => ['DELETE', '/api/videos/{video}'],
            'annotation list' => ['GET', '/api/videos/{video}/annotations'],
            'annotation store' => ['POST', '/api/videos/{video}/annotations'],
            'annotation delete' => ['DELETE', '/api/annotations/{annotation}'],
            'share store' => ['POST', '/api/annotations/{annotation}/share'],
            'comment list' => ['GET', '/api/annotations/{annotation}/comments'],
            'comment store' => ['POST', '/api/annotations/{annotation}/comments'],
            'clip store' => ['POST', '/api/clips'],
            'clip show' => ['GET', '/api/clips/{clip}'],
        ];
    }

    /**
     * @dataProvider protectedEndpoints
     */
    public function test_outsider_gets_403(string $method, string $uri): void
    {
        $clip = Clip::factory()->for($this->video)->create();

        $uri = str_replace(
            ['{video}', '{annotation}', '{clip}'],
            [$this->video->id, $this->annotation->id, $clip->id],
            $uri,
        );

        Sanctum::actingAs($this->outsider);

        $this->json($method, $uri, $this->validPayload($uri))
            ->assertForbidden()
            ->assertExactJson(['message' => 'この操作は許可されていません']);
    }

    public function test_owner_can_access_own_video_resources(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->owner);

        $this->getJson("/api/videos/{$this->video->id}")->assertOk();
        $this->getJson("/api/videos/{$this->video->id}/annotations")->assertOk();
        $this->postJson("/api/annotations/{$this->annotation->id}/share", [])->assertCreated();
        $this->postJson('/api/clips', $this->validPayload('/api/clips'))->assertStatus(202);
        $this->deleteJson("/api/annotations/{$this->annotation->id}")->assertOk();
        $this->deleteJson("/api/videos/{$this->video->id}")->assertOk();
    }

    public function test_team_member_can_view_and_share_but_not_delete_team_video(): void
    {
        [$team, $member] = $this->teamWithMember();
        $teamVideo = Video::factory()->for($team->owner)->create(['team_id' => $team->id]);
        $annotation = Annotation::factory()->for($teamVideo)->create();

        Sanctum::actingAs($member);

        $this->getJson("/api/videos/{$teamVideo->id}")->assertOk()->assertJsonPath('data.team.id', $team->id);
        $this->getJson("/api/videos/{$teamVideo->id}/annotations")->assertOk();
        $this->postJson("/api/annotations/{$annotation->id}/share", [])->assertCreated();
        $this->deleteJson("/api/videos/{$teamVideo->id}")->assertForbidden();
    }

    public function test_team_owner_can_delete_member_uploaded_team_video(): void
    {
        [$team, $member] = $this->teamWithMember();
        $teamVideo = Video::factory()->for($member)->create(['team_id' => $team->id]);

        Sanctum::actingAs($team->owner);

        $this->deleteJson("/api/videos/{$teamVideo->id}")->assertOk();
    }

    public function test_uploader_can_delete_own_team_video(): void
    {
        [$team, $member] = $this->teamWithMember();
        $teamVideo = Video::factory()->for($member)->create(['team_id' => $team->id]);

        Sanctum::actingAs($member);

        $this->deleteJson("/api/videos/{$teamVideo->id}")->assertOk();
    }

    public function test_outsider_cannot_access_team_video(): void
    {
        [$team] = $this->teamWithMember();
        $teamVideo = Video::factory()->for($team->owner)->create(['team_id' => $team->id]);

        Sanctum::actingAs($this->outsider);

        $this->getJson("/api/videos/{$teamVideo->id}")->assertForbidden();
    }

    public function test_only_comment_author_can_delete_comment(): void
    {
        [$team, $member] = $this->teamWithMember();
        $teamVideo = Video::factory()->for($team->owner)->create(['team_id' => $team->id]);
        $annotation = Annotation::factory()->for($teamVideo)->create();
        $comment = Comment::create([
            'annotation_id' => $annotation->id,
            'user_id' => $member->id,
            'body' => 'ナイスプレー',
        ]);

        Sanctum::actingAs($team->owner);
        $this->deleteJson("/api/comments/{$comment->id}")->assertForbidden();

        Sanctum::actingAs($member);
        $this->deleteJson("/api/comments/{$comment->id}")->assertOk();
    }

    /**
     * @return array{Team, User}
     */
    private function teamWithMember(): array
    {
        $team = Team::factory()->create();
        $member = User::factory()->create();
        $team->memberships()->create([
            'user_id' => $member->id,
            'role' => TeamMember::ROLE_MEMBER,
            'joined_at' => now(),
        ]);

        return [$team, $member];
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(string $uri): array
    {
        return match (true) {
            str_ends_with($uri, '/annotations') => [
                'start_seconds' => 1,
                'end_seconds' => 5,
                'canvas_data' => ['canvas_width' => 1280, 'canvas_height' => 720, 'objects' => []],
            ],
            str_ends_with($uri, '/comments') => ['body' => 'コメント'],
            $uri === '/api/clips' => [
                'video_id' => $this->video->id,
                'title' => 'クリップ',
                'start_seconds' => 1,
                'end_seconds' => 5,
            ],
            default => [],
        };
    }
}
