<?php

namespace App\Services;

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

        return $comment->load('user');
    }

    public function delete(Comment $comment): void
    {
        $comment->delete();
    }
}
