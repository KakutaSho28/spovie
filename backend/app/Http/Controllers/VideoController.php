<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVideoRequest;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VideoController extends Controller
{
    /**
     * VIDEO-01 動画一覧取得
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $videos = $request->user()
            ->videos()
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return VideoResource::collection($videos);
    }

    /**
     * VIDEO-02 動画登録
     */
    public function store(StoreVideoRequest $request): JsonResponse
    {
        $videoId = $this->extractYoutubeVideoId($request->youtube_url);

        $video = $request->user()->videos()->create([
            'youtube_video_id' => $videoId,
            'title' => $request->title,
        ]);

        return (new VideoResource($video))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * VIDEO-03 動画削除
     */
    public function destroy(Request $request, Video $video): JsonResponse
    {
        if ($video->user_id !== $request->user()->id) {
            return response()->json(['message' => 'この操作は許可されていません'], 403);
        }

        $video->delete();

        return response()->json(['message' => '動画を削除しました']);
    }

    /**
     * YouTube URLから動画IDを抽出する
     * 対応形式: watch?v= / youtu.be/ / embed/
     */
    private function extractYoutubeVideoId(string $url): string
    {
        preg_match('/(?:v=|youtu\.be\/|embed\/)([a-zA-Z0-9_-]{11})/', $url, $matches);

        return $matches[1];
    }
}
