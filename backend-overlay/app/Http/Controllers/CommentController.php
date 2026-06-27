<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Annotation;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommentController extends Controller
{
    public function index(Request $request, Annotation $annotation): AnonymousResourceCollection|JsonResponse
    {
        if (! $annotation->video->canBeAccessedBy($request->user())) {
            return response()->json(['message' => 'この操作は許可されていません'], 403);
        }

        $comments = $annotation->comments()
            ->with('user')
            ->orderBy('created_at')
            ->get();

        return CommentResource::collection($comments);
    }

    public function store(StoreCommentRequest $request, Annotation $annotation): JsonResponse
    {
        if (! $annotation->video->canBeAccessedBy($request->user())) {
            return response()->json(['message' => 'この操作は許可されていません'], 403);
        }

        $comment = $annotation->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->body,
        ]);

        return (new CommentResource($comment->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        if ($comment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'この操作は許可されていません'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'コメントを削除しました']);
    }
}
