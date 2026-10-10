<?php

namespace App\Events;

use App\Http\Resources\AnnotationSummaryResource;
use App\Models\Annotation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * 新しいアノテーションを、その動画のアノテーション一覧を見ている全員へ通知する（キュー経由）。
 */
class AnnotationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Annotation $annotation) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("video.{$this->annotation->video_id}");
    }

    public function broadcastAs(): string
    {
        return 'annotation.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['annotation' => (new AnnotationSummaryResource($this->annotation))->resolve(new Request())];
    }
}
