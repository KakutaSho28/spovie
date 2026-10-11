<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class CommentDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $commentId,
        public int $annotationId,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("annotation.{$this->annotationId}");
    }

    public function broadcastAs(): string
    {
        return 'comment.deleted';
    }

    /**
     * @return array<string, int>
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->commentId, 'annotation_id' => $this->annotationId];
    }
}
