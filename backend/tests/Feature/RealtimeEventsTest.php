<?php

namespace Tests\Feature;

use App\Events\AnnotationCreated;
use App\Events\CommentCreated;
use App\Events\CommentDeleted;
use App\Models\Annotation;
use App\Models\Comment;
use App\Models\User;
use App\Models\Video;
use App\Services\AnnotationService;
use App\Services\CommentService;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealtimeEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_comment_broadcasts_comment_created(): void
    {
        Event::fake([CommentCreated::class]);
        $annotation = Annotation::factory()->create();
        $author = User::factory()->create(['name' => '山田']);

        $comment = app(CommentService::class)->create($annotation, $author, '3番が遅い');

        Event::assertDispatched(CommentCreated::class, fn (CommentCreated $e) => $e->comment->is($comment));
    }

    public function test_deleting_a_comment_broadcasts_comment_deleted_with_ids_captured_before_delete(): void
    {
        Event::fake([CommentDeleted::class]);
        $annotation = Annotation::factory()->create();
        $comment = Comment::create(['annotation_id' => $annotation->id, 'user_id' => User::factory()->create()->id, 'body' => 'x']);
        $id = $comment->id;

        app(CommentService::class)->delete($comment);

        Event::assertDispatched(CommentDeleted::class, fn (CommentDeleted $e) => $e->commentId === $id && $e->annotationId === $annotation->id);
    }

    public function test_creating_an_annotation_broadcasts_annotation_created(): void
    {
        Event::fake([AnnotationCreated::class]);
        $video = Video::factory()->create();

        $annotation = app(AnnotationService::class)->create($video, [
            'start_seconds' => 1, 'end_seconds' => 4, 'canvas_data' => ['objects' => []],
        ]);

        Event::assertDispatched(AnnotationCreated::class, fn (AnnotationCreated $e) => $e->annotation->is($annotation));
    }

    public function test_events_go_to_private_channels_with_expected_names_and_payloads(): void
    {
        $annotation = Annotation::factory()->create(['comment' => 'メモ']);
        $user = User::factory()->create(['name' => '山田']);
        $comment = Comment::create(['annotation_id' => $annotation->id, 'user_id' => $user->id, 'body' => 'こんにちは']);

        $created = new CommentCreated($comment);
        $this->assertEquals(new PrivateChannel("annotation.{$annotation->id}"), $created->broadcastOn());
        $this->assertSame('comment.created', $created->broadcastAs());
        $payload = $created->broadcastWith()['comment'];
        $this->assertSame('こんにちは', $payload['body']);
        $this->assertSame(['id' => $user->id, 'name' => '山田'], $payload['user']);
        $this->assertSame($annotation->id, $comment->annotation_id);

        $deleted = new CommentDeleted(5, $annotation->id);
        $this->assertEquals(new PrivateChannel("annotation.{$annotation->id}"), $deleted->broadcastOn());
        $this->assertSame('comment.deleted', $deleted->broadcastAs());
        $this->assertSame(['id' => 5, 'annotation_id' => $annotation->id], $deleted->broadcastWith());

        $new = new AnnotationCreated($annotation);
        $this->assertEquals(new PrivateChannel("video.{$annotation->video_id}"), $new->broadcastOn());
        $this->assertSame('annotation.created', $new->broadcastAs());
    }

    public function test_annotation_payload_excludes_canvas_data_to_stay_under_pusher_limit(): void
    {
        $annotation = Annotation::factory()->create(['canvas_data' => ['objects' => array_fill(0, 500, ['type' => 'Path'])]]);

        $payload = (new AnnotationCreated($annotation))->broadcastWith();

        $this->assertArrayNotHasKey('canvas_data', $payload['annotation']);
        $this->assertLessThan(1024, strlen(json_encode($payload)));
    }

    public function test_all_events_are_queued_for_the_worker(): void
    {
        foreach ([CommentCreated::class, CommentDeleted::class, AnnotationCreated::class] as $event) {
            $this->assertTrue(is_subclass_of($event, ShouldBroadcast::class), "$event must implement ShouldBroadcast");
        }

        Queue::fake();
        app(CommentService::class)->create(Annotation::factory()->create(), User::factory()->create(), 'queued');

        Queue::assertPushed(BroadcastEvent::class, fn ($job) => $job->event instanceof CommentCreated);
    }

    public function test_http_actions_trigger_the_broadcasts(): void
    {
        Event::fake([CommentCreated::class, AnnotationCreated::class]);
        $video = Video::factory()->create();
        Sanctum::actingAs($video->user);

        $annotationId = $this->postJson("/api/videos/{$video->id}/annotations", [
            'start_seconds' => 1, 'end_seconds' => 5, 'canvas_data' => ['objects' => []],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/annotations/{$annotationId}/comments", ['body' => 'やあ'])->assertCreated();

        Event::assertDispatched(AnnotationCreated::class);
        Event::assertDispatched(CommentCreated::class);
    }
}
