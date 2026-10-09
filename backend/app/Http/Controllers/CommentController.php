<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Annotation;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommentController extends Controller
{
    public function index(Annotation $annotation): AnonymousResourceCollection
    {
        $this->authorize('view', $annotation);

        $comments = $annotation->comments()
            ->with('user')
            ->orderBy('created_at')
            ->get();

        return CommentResource::collection($comments);
    }

    public function store(StoreCommentRequest $request, Annotation $annotation): JsonResponse
    {
        $this->authorize('view', $annotation);

        $comment = $annotation->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->body,
        ]);

        return (new CommentResource($comment->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return response()->json(['message' => 'コメントを削除しました']);
    }
}
