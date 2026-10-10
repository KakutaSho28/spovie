<?php

namespace App\Services;

use App\Events\CommentCreated;
use App\Events\CommentDeleted;
use App\Models\Annotation;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class CommentService
{
    /**
     * @return Collection<int, Comment>
     */
    public function listFor(Annotation $annotation): Collection
    {
        return $annotation->comments()->with('user')->orderBy('created_at')->get();
    }

    public function create(Annotation $annotation, User $author, string $body): Comment
    {
        $comment = $annotation->comments()->create([
            'user_id' => $author->id,
            'body' => $body,
        ]);

        // 投稿者本人のブラウザ（X-Socket-ID）には送らない。クライアント側でも id で重複排除する
        broadcast(new CommentCreated($comment))->toOthers();

        return $comment->load('user');
    }

    public function delete(Comment $comment): void
    {
        $commentId = $comment->id;
        $annotationId = $comment->annotation_id;

        $comment->delete();

        broadcast(new CommentDeleted($commentId, $annotationId))->toOthers();
    }
}
