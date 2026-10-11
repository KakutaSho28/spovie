<?php

namespace Tests\Feature;

use App\Models\Annotation;
use App\Models\Clip;
use App\Models\ShareLink;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * docs/api-examples.php の「レスポンス例」が、実際の API のレスポンスと同じ構造（キー）であることを確認する。
 * 例だけが古くなって、ドキュメントが嘘をつくのを防ぐ。値は比較しない（null の項目は何でも許可）。
 */
class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array<string, mixed>> */
    private array $examples;

    protected function setUp(): void
    {
        parent::setUp();
        $this->examples = require base_path('docs/api-examples.php');
        Event::fake(); // ブロードキャストは別のテストで検証済み
        Storage::fake(config('filesystems.default'));
    }

    private function assertDocumented(string $key, TestResponse $response): void
    {
        $status = $response->getStatusCode();
        $documented = $this->examples[$key]['responses'][$status] ?? null;
        $this->assertNotNull($documented, "{$key} が返した {$status} が api-examples.php に載っていません");

        if (array_key_exists('example', $documented)) {
            $this->assertShape($documented['example'], $response->json(), "{$key} [{$status}]");
        }
    }

    private function assertShape(mixed $expected, mixed $actual, string $path): void
    {
        if ($expected === null || $path === 'canvas_data') {
            return; // null は「任意」、canvas_data は Fabric の自由形式
        }
        if (! is_array($expected)) {
            $this->assertFalse(is_array($actual), "{$path}: スカラーのはずが配列です");

            return;
        }
        $this->assertIsArray($actual, "{$path}: 配列/オブジェクトのはずです");

        if (array_is_list($expected)) {
            if ($expected !== [] && $actual !== []) {
                $this->assertShape($expected[0], $actual[0], "{$path}[0]");
            }

            return;
        }

        $this->assertEqualsCanonicalizing(array_keys($expected), array_keys($actual), "{$path}: キーがドキュメントの例と一致しません");
        foreach ($expected as $key => $value) {
            $this->assertShape($value, $actual[$key], $key === 'canvas_data' ? 'canvas_data' : "{$path}.{$key}");
        }
    }

    private function teamWithMember(User $member): Team
    {
        $team = Team::factory()->create();
        $team->memberships()->create(['user_id' => $member->id, 'role' => TeamMember::ROLE_MEMBER, 'joined_at' => now()]);

        return $team;
    }

    public function test_auth_endpoints(): void
    {
        $r = $this->postJson('/api/auth/register', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'password123', 'password_confirmation' => 'password123']);
        $this->assertDocumented('POST /api/auth/register', $r->assertCreated());

        $this->assertDocumented('POST /api/auth/login', $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'password123'])->assertOk());
        $this->assertDocumented('POST /api/auth/login', $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'wrong-password'])->assertUnauthorized());

        $token = $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'password123'])->json('data.token');
        $this->assertDocumented('POST /api/auth/logout', $this->withToken($token)->postJson('/api/auth/logout')->assertOk());
    }

    public function test_video_endpoints(): void
    {
        $user = User::factory()->create();
        $team = $this->teamWithMember($user);
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/videos', ['title' => 't', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ', 'team_id' => $team->id]);
        $this->assertDocumented('POST /api/videos', $created->assertCreated());
        $id = $created->json('data.id');

        $this->assertDocumented('GET /api/videos', $this->getJson('/api/videos')->assertOk());
        $this->assertDocumented('GET /api/videos/{video}', $this->getJson("/api/videos/{$id}")->assertOk());
        $this->assertDocumented('GET /api/videos/{video}', $this->actingAs(User::factory()->create(), 'sanctum')->getJson("/api/videos/{$id}")->assertForbidden());

        Sanctum::actingAs($user);
        $upload = $this->postJson('/api/videos/upload', ['title' => 'u', 'file' => UploadedFile::fake()->create('a.mp4', 10, 'video/mp4')]);
        $this->assertDocumented('POST /api/videos/upload', $upload->assertCreated());

        $this->assertDocumented('GET /api/teams/{team}/videos', $this->getJson("/api/teams/{$team->id}/videos")->assertOk());
    }

    public function test_annotation_share_and_comment_endpoints(): void
    {
        $video = Video::factory()->create();
        Sanctum::actingAs($video->user);

        $store = $this->postJson("/api/videos/{$video->id}/annotations", ['start_seconds' => 1, 'end_seconds' => 5, 'canvas_data' => ['canvas_width' => 1280, 'canvas_height' => 720, 'objects' => []], 'comment' => 'x']);
        $this->assertDocumented('POST /api/videos/{video}/annotations', $store->assertCreated());
        $annotationId = $store->json('data.id');

        $this->assertDocumented('GET /api/videos/{video}/annotations', $this->getJson("/api/videos/{$video->id}/annotations")->assertOk());

        $share = $this->postJson("/api/annotations/{$annotationId}/share", []);
        $this->assertDocumented('POST /api/annotations/{annotation}/share', $share->assertCreated());
        $this->assertDocumented('GET /api/share/{token}', $this->getJson('/api/share/' . $share->json('data.token'))->assertOk());
        $this->assertDocumented('GET /api/share/{token}', $this->getJson('/api/share/unknown')->assertNotFound());
        $expired = ShareLink::factory()->create(['expires_at' => now()->subMinute()]);
        $this->assertDocumented('GET /api/share/{token}', $this->getJson("/api/share/{$expired->token}")->assertStatus(410));

        $comment = $this->postJson("/api/annotations/{$annotationId}/comments", ['body' => 'やあ']);
        $this->assertDocumented('POST /api/annotations/{annotation}/comments', $comment->assertCreated());
        $this->assertDocumented('GET /api/annotations/{annotation}/comments', $this->getJson("/api/annotations/{$annotationId}/comments")->assertOk());
        $this->assertDocumented('DELETE /api/comments/{comment}', $this->deleteJson('/api/comments/' . $comment->json('data.id'))->assertOk());
        $this->assertDocumented('DELETE /api/annotations/{annotation}', $this->deleteJson("/api/annotations/{$annotationId}")->assertOk());
        $this->assertDocumented('DELETE /api/videos/{video}', $this->deleteJson("/api/videos/{$video->id}")->assertOk());
    }

    public function test_team_endpoints(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $created = $this->postJson('/api/teams', ['name' => 'FC']);
        $this->assertDocumented('POST /api/teams', $created->assertCreated());
        $teamId = $created->json('data.id');
        $token = $created->json('data.invite_token');

        $this->assertDocumented('GET /api/teams', $this->getJson('/api/teams')->assertOk());
        $this->assertDocumented('GET /api/teams/{team}', $this->getJson("/api/teams/{$teamId}")->assertOk());

        $member = User::factory()->create();
        Sanctum::actingAs($member);
        $this->assertDocumented('GET /api/teams/invite/{token}', $this->getJson("/api/teams/invite/{$token}")->assertOk());
        $this->assertDocumented('POST /api/teams/join', $this->postJson('/api/teams/join', ['invite_token' => $token])->assertOk());
        $this->assertDocumented('POST /api/teams/join', $this->postJson('/api/teams/join', ['invite_token' => 'nope'])->assertNotFound());
        $this->assertDocumented('DELETE /api/teams/{team}/members/{user}', $this->deleteJson("/api/teams/{$teamId}/members/{$owner->id}")->assertForbidden());

        Sanctum::actingAs($owner);
        $this->assertDocumented('DELETE /api/teams/{team}/members/{user}', $this->deleteJson("/api/teams/{$teamId}/members/{$owner->id}")->assertUnprocessable());
        $this->assertDocumented('DELETE /api/teams/{team}/members/{user}', $this->deleteJson("/api/teams/{$teamId}/members/{$member->id}")->assertOk());
        $this->assertDocumented('DELETE /api/teams/{team}', $this->deleteJson("/api/teams/{$teamId}")->assertOk());
    }

    public function test_clip_and_health_endpoints(): void
    {
        Queue::fake();
        $upload = Video::factory()->upload()->create();
        $youtube = Video::factory()->for($upload->user)->create();
        Sanctum::actingAs($upload->user);

        $clip = $this->postJson('/api/clips', ['video_id' => $upload->id, 'title' => 'ゴール', 'start_seconds' => 5, 'end_seconds' => 15]);
        $this->assertDocumented('POST /api/clips', $clip->assertStatus(202));
        $this->assertDocumented('POST /api/clips', $this->postJson('/api/clips', ['video_id' => $youtube->id, 'title' => 'x', 'start_seconds' => 1, 'end_seconds' => 2])->assertUnprocessable());

        $done = Clip::factory()->done()->create(['video_id' => $upload->id]);
        $this->assertDocumented('GET /api/clips/{clip}', $this->getJson("/api/clips/{$done->id}")->assertOk());

        Storage::disk(config('filesystems.default'))->put('clips/test.mp4', 'x');
        $this->assertArrayHasKey(404, $this->examples['GET /api/clips/{clip}/download/{token}']['responses']);
        $this->getJson("/api/clips/{$done->id}/download/wrong")->assertNotFound();

        $this->assertDocumented('GET /api/health', $this->getJson('/api/health')->assertOk());
    }
}
