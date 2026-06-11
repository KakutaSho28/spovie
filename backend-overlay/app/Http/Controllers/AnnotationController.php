<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnnotationRequest;
use App\Http\Resources\AnnotationResource;
use App\Models\Annotation;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnnotationController extends Controller
{
    /**
     * ANNOTATION-02 アノテーション一覧取得
     */
    public function index(Request $request, Video $video): AnonymousResourceCollection|JsonResponse
    {
        if ($video->user_id !== $request->user()->id) {
            return response()->json(['message' => 'この操作は許可されていません'], 403);
        }

        $annotations = $video->annotations()->orderByDesc('created_at')->get();

        return AnnotationResource::collection($annotations);
    }

    /**
     * ANNOTATION-01 アノテーション保存
     */
    public function store(StoreAnnotationRequest $request, Video $video): JsonResponse
    {
        if ($video->user_id !== $request->user()->id) {
            return response()->json(['message' => 'この操作は許可されていません'], 403);
        }

        $annotation = $video->annotations()->create($request->validated());

        return (new AnnotationResource($annotation))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * ANNOTATION-03 アノテーション削除
     */
    public function destroy(Request $request, Annotation $annotation): JsonResponse
    {
        if ($annotation->video->user_id !== $request->user()->id) {
            return response()->json(['message' => 'この操作は許可されていません'], 403);
        }

        $annotation->delete();

        return response()->json(['message' => 'アノテーションを削除しました']);
    }
}
