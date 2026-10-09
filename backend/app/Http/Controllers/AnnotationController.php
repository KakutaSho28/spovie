<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnnotationRequest;
use App\Http\Resources\AnnotationResource;
use App\Models\Annotation;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnnotationController extends Controller
{
    /**
     * ANNOTATION-02 アノテーション一覧取得
     */
    public function index(Video $video): AnonymousResourceCollection
    {
        $this->authorize('view', $video);

        $annotations = $video->annotations()
            ->withCount('comments')
            ->orderByDesc('created_at')
            ->get();

        return AnnotationResource::collection($annotations);
    }

    /**
     * ANNOTATION-01 アノテーション保存
     */
    public function store(StoreAnnotationRequest $request, Video $video): JsonResponse
    {
        $this->authorize('view', $video);

        $annotation = $video->annotations()->create($request->validated());

        return (new AnnotationResource($annotation))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * ANNOTATION-03 アノテーション削除
     */
    public function destroy(Annotation $annotation): JsonResponse
    {
        $this->authorize('delete', $annotation);

        $annotation->delete();

        return response()->json(['message' => 'アノテーションを削除しました']);
    }
}
