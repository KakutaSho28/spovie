<?php

namespace Tests\Feature\Services;

use App\Models\Annotation;
use App\Models\User;
use App\Models\Video;
use App\Services\AnnotationService;
use App\Services\CommentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnotationCommentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_annotation_create_list_delete_with_comment_counts(): void
    {
        $video = Video::factory()->create();
        $annotations = app(AnnotationService::class);
        $comments = app(CommentService::class);

        $a = $annotations->create($video, ['start_seconds' => 1, 'end_seconds' => 4, 'canvas_data' => ['objects' => []]]);
        $comments->create($a, User::factory()->create(), 'いいね');

        $list = $annotations->listFor($video);
        $this->assertCount(1, $list);
        $this->assertSame(1, $list->first()->comments_count);

        $annotations->delete($a);
        $this->assertSame(0, Annotation::count());
    }

    public function test_comment_create_loads_author_and_lists_oldest_first(): void
    {
        $annotation = Annotation::factory()->create();
        $user = User::factory()->create();
        $service = app(CommentService::class);

        $first = $service->create($annotation, $user, '1つめ');
        $service->create($annotation, $user, '2つめ');

        $this->assertTrue($first->relationLoaded('user'));
        $this->assertSame(['1つめ', '2つめ'], $service->listFor($annotation)->pluck('body')->all());
    }
}
