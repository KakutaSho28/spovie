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
        $user = $request->user();
        $teamIds = $user->teams()->pluck('teams.id');

        $videos = Video::query()
            ->with('team')
            ->where(function ($query) use ($user, $teamIds) {
                $query
                    ->where(function ($personal) use ($user) {
                        $personal->where('user_id', $user->id)->whereNull('team_id');
                    })
                    ->orWhereIn('team_id', $teamIds);
            })
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return VideoResource::collection($videos);
    }

    /**
     * VIDEO-02 動画登録
     */
    public function store(StoreVideoRequest $request): JsonResponse
    {
        if (! $this->canUseTeam($request)) {
            return response()->json(['message' => 'このチームに動画を追加できません'], 403);
        }

        $videoId = $this->extractYoutubeVideoId($request->youtube_url);

        $video = $request->user()->videos()->create([
            'team_id' => $request->team_id,
            'type' => Video::TYPE_YOUTUBE,
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
        if (! $this->canManageVideo($request, $video)) {
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

    private function canUseTeam(StoreVideoRequest $request): bool
    {
        if (! $request->team_id) {
            return true;
        }

        return $request->user()
            ->teams()
            ->where('teams.id', $request->team_id)
            ->exists();
    }

    private function canManageVideo(Request $request, Video $video): bool
    {
        if ($video->team_id) {
            return $video->team()->whereHas('memberships', function ($query) use ($request) {
                $query
                    ->where('user_id', $request->user()->id)
                    ->where('role', 'owner');
            })->exists();
        }

        return $video->user_id === $request->user()->id;
    }
}
