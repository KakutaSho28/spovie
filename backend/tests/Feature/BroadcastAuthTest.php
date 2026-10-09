<?php

namespace Tests\Feature;

use App\Models\Annotation;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * チャンネル認可。Pusher サーバーへの通信は発生しない（署名はローカルで計算される）ので、ダミーのキーで検証できる。
 */
class BroadcastAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => '12345',
            'broadcasting.connections.pusher.options.cluster' => 'ap3',
        ]);

        // チャンネル定義は起動時の既定ドライバ（テストでは log）に登録されるため、
        // pusher に切り替えたあとで pusher 側にも登録し直す（本番は起動時から pusher なので不要）
        require base_path('routes/channels.php');
    }

    private function auth(string $channel): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-{$channel}",
        ]);
    }

    public function test_auth_endpoint_is_under_api_and_requires_a_bearer_token(): void
    {
        $annotation = Annotation::factory()->create();

        $this->auth("annotation.{$annotation->id}")->assertUnauthorized();
        $this->postJson('/broadcasting/auth', [])->assertNotFound();
    }

    public function test_owner_is_authorized_for_own_video_channels(): void
    {
        $annotation = Annotation::factory()->create();
        Sanctum::actingAs($annotation->video->user);

        $this->auth("annotation.{$annotation->id}")->assertOk()->assertJsonStructure(['auth']);
        $this->auth("video.{$annotation->video_id}")->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_outsider_is_rejected_for_both_channels(): void
    {
        $annotation = Annotation::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->auth("annotation.{$annotation->id}")->assertForbidden();
        $this->auth("video.{$annotation->video_id}")->assertForbidden();
    }

    public function test_team_member_is_authorized_but_non_member_is_not(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->create();
        $team->memberships()->create(['user_id' => $member->id, 'role' => TeamMember::ROLE_MEMBER, 'joined_at' => now()]);
        $video = Video::factory()->for($team->owner)->create(['team_id' => $team->id]);
        $annotation = Annotation::factory()->for($video)->create();

        Sanctum::actingAs($member);
        $this->auth("annotation.{$annotation->id}")->assertOk();
        $this->auth("video.{$video->id}")->assertOk();

        Sanctum::actingAs(User::factory()->create());
        $this->auth("annotation.{$annotation->id}")->assertForbidden();
    }

    public function test_unknown_resources_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->auth('annotation.99999')->assertForbidden();
        $this->auth('video.99999')->assertForbidden();
    }
}
