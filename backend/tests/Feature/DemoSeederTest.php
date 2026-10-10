<?php

namespace Tests\Feature;

use App\Events\AnnotationCreated;
use App\Events\CommentCreated;
use App\Events\CommentDeleted;
use App\Models\Annotation;
use App\Models\Comment;
use App\Models\Team;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_db_seed_creates_demo_users_team_videos_annotations_and_comments(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(2, User::count());
        $team = Team::firstOrFail();
        $this->assertSame(DemoSeeder::TEAM_NAME, $team->name);
        $this->assertSame(2, $team->memberships()->count());
        $this->assertSame('owner', $team->memberships()->where('user_id', $team->owner_id)->first()->role);
        $this->assertSame(2, Video::count());
        $this->assertSame(3, Annotation::count());
        $this->assertSame(3, Comment::count());
    }

    public function test_seeding_is_idempotent(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(
            [2, 1, 2, 2, 3, 3],
            [User::count(), Team::count(), $this->membershipCount(), Video::count(), Annotation::count(), Comment::count()],
        );
    }

    public function test_demo_data_is_youtube_only_with_no_uploaded_files(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(0, Video::where('type', Video::TYPE_UPLOAD)->count());
        $this->assertSame(0, Video::whereNotNull('file_path')->count());
        Video::all()->each(fn (Video $video) => $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{11}$/', $video->youtube_video_id));
    }

    public function test_demo_users_can_log_in_and_see_the_team_videos(): void
    {
        $this->seed(DemoSeeder::class);

        foreach ([DemoSeeder::ALICE_EMAIL, DemoSeeder::BOB_EMAIL] as $email) {
            $token = $this->postJson('/api/auth/login', ['email' => $email, 'password' => config('demo.password')])
                ->assertOk()
                ->json('data.token');

            $this->withToken($token)->getJson('/api/videos')->assertOk()->assertJsonPath('meta.total', 2);
            $this->withToken($token)->getJson('/api/teams')->assertOk()->assertJsonPath('data.0.name', DemoSeeder::TEAM_NAME);
        }
    }

    public function test_reseeding_resets_the_demo_password(): void
    {
        $this->seed(DemoSeeder::class);
        User::where('email', DemoSeeder::ALICE_EMAIL)->update(['password' => 'changed']);

        $this->seed(DemoSeeder::class);

        $this->postJson('/api/auth/login', ['email' => DemoSeeder::ALICE_EMAIL, 'password' => config('demo.password')])->assertOk();
    }

    public function test_seeding_does_not_broadcast_events(): void
    {
        Event::fake([CommentCreated::class, CommentDeleted::class, AnnotationCreated::class]);

        $this->seed(DemoSeeder::class);

        Event::assertNotDispatched(CommentCreated::class);
        Event::assertNotDispatched(CommentDeleted::class);
        Event::assertNotDispatched(AnnotationCreated::class);
    }

    public function test_demo_annotations_have_a_canvas_the_frontend_can_restore(): void
    {
        $this->seed(DemoSeeder::class);

        $withDrawing = Annotation::all()->first(fn (Annotation $a) => ! empty($a->canvas_data['objects']));
        $this->assertSame(1280, $withDrawing->canvas_data['canvas_width']);
        $this->assertSame(['Circle', 'IText'], array_column($withDrawing->canvas_data['objects'], 'type'));
    }

    private function membershipCount(): int
    {
        return Team::firstOrFail()->memberships()->count();
    }
}
