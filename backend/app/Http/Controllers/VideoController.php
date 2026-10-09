<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListVideosRequest;
use App\Http\Requests\StoreVideoRequest;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use App\Services\VideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VideoController extends Controller
{
    public function __construct(private readonly VideoService $videos) {}

    /** VIDEO-01 動画一覧取得 */
    public function index(ListVideosRequest $request): AnonymousResourceCollection
    {
        return VideoResource::collection($this->videos->paginateVisibleTo(
            $request->user(),
            $request->integer('per_page', 20),
            $request->input('scope', 'all'),
            $request->filled('team_id') ? $request->integer('team_id') : null,
        ));
    }

    /** VIDEO-04 動画詳細取得 */
    public function show(Video $video): VideoResource
    {
        $this->authorize('view', $video);

        return new VideoResource($video->load('team'));
    }

    /** VIDEO-02 動画登録 */
    public function store(StoreVideoRequest $request): JsonResponse
    {
        $video = $this->videos->createYoutube(
            $request->user(),
            $request->title,
            $request->youtube_url,
            $request->team_id,
        );

        return (new VideoResource($video))->response()->setStatusCode(201);
    }

    /** VIDEO-03 動画削除 */
    public function destroy(Video $video): JsonResponse
    {
        $this->authorize('delete', $video);
        $this->videos->delete($video);

        return response()->json(['message' => '動画を削除しました']);
    }
}
