<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Annotation;
use App\Models\Comment;
use App\Services\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommentController extends Controller
{
    public function __construct(private readonly CommentService $comments) {}

    public function index(Annotation $annotation): AnonymousResourceCollection
    {
        $this->authorize('view', $annotation);

        return CommentResource::collection($this->comments->listFor($annotation));
    }

    public function store(StoreCommentRequest $request, Annotation $annotation): JsonResponse
    {
        $this->authorize('view', $annotation);

        return (new CommentResource($this->comments->create($annotation, $request->user(), $request->body)))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);
        $this->comments->delete($comment);

        return response()->json(['message' => 'コメントを削除しました']);
    }
}
