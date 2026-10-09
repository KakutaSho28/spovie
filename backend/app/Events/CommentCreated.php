<?php

namespace App\Events;

use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

/**
 * コメント投稿を、同じアノテーションを見ている全員へ配信する（キュー経由）。
 */
class CommentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Comment $comment) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("annotation.{$this->comment->annotation_id}");
    }

    public function broadcastAs(): string
    {
        return 'comment.created';
    }

    /**
     * is_own は閲覧者ごとに異なるため、クライアントが user.id から再計算する。
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['comment' => (new CommentResource($this->comment->loadMissing('user')))->resolve(new Request())];
    }
}
