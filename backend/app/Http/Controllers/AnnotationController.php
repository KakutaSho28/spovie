<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnnotationRequest;
use App\Http\Resources\AnnotationResource;
use App\Models\Annotation;
use App\Models\Video;
use App\Services\AnnotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnnotationController extends Controller
{
    public function __construct(private readonly AnnotationService $annotations) {}

    /** ANNOTATION-02 アノテーション一覧取得 */
    public function index(Video $video): AnonymousResourceCollection
    {
        $this->authorize('view', $video);

        return AnnotationResource::collection($this->annotations->listFor($video));
    }

    /** ANNOTATION-01 アノテーション保存 */
    public function store(StoreAnnotationRequest $request, Video $video): JsonResponse
    {
        $this->authorize('view', $video);

        return (new AnnotationResource($this->annotations->create($video, $request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    /** ANNOTATION-03 アノテーション削除 */
    public function destroy(Annotation $annotation): JsonResponse
    {
        $this->authorize('delete', $annotation);
        $this->annotations->delete($annotation);

        return response()->json(['message' => 'アノテーションを削除しました']);
    }
}
