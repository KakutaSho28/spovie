<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClipRequest;
use App\Http\Resources\ClipResource;
use App\Jobs\ProcessClipJob;
use App\Models\Clip;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClipController extends Controller
{
    /**
     * 切り抜きジョブの作成
     * POST /api/clips
     */
    public function store(StoreClipRequest $request): JsonResponse
    {
        $video = Video::findOrFail($request->video_id);

        if (! $video->canBeAccessedBy($request->user())) {
            return response()->json(['message' => 'この操作は許可されていません'], 403);
        }

        if (! $video->isUpload()) {
            return response()->json([
                'message' => 'YouTube動画は切り抜き保存できません。直接アップロードした動画のみ対応しています。',
            ], 422);
        }

        $clip = Clip::create([
            'video_id' => $video->id,
            'annotation_id' => $request->annotation_id,
            'title' => $request->title,
            'start_seconds' => $request->start_seconds,
            'end_seconds' => $request->end_seconds,
            'status' => Clip::STATUS_PROCESSING,
        ]);

        ProcessClipJob::dispatch($clip);

        return (new ClipResource($clip))
            ->response()
            ->setStatusCode(202);
    }

    /**
     * 切り抜き状態の取得（ポーリング用）
     * GET /api/clips/{clip}
     */
    public function show(Request $request, Clip $clip): JsonResponse|ClipResource
    {
        if (! $clip->video->canBeAccessedBy($request->user())) {
            return response()->json(['message' => 'この操作は許可されていません'], 403);
        }

        return new ClipResource($clip);
    }

    /**
     * 切り抜き動画のダウンロード（認証不要 = LINE共有用）
     * GET /api/clips/{clip}/download
     */
    public function download(Clip $clip): StreamedResponse|JsonResponse
    {
        if (! $clip->isDone() || ! $clip->file_path) {
            return response()->json(['message' => 'クリップが見つかりません'], 404);
        }

        $disk = Storage::disk(config('filesystems.default'));

        if (! $disk->exists($clip->file_path)) {
            return response()->json(['message' => 'クリップが見つかりません'], 404);
        }

        $fileName = Str::slug($clip->title, '_') ?: 'clip';

        return $disk->download(
            $clip->file_path,
            $fileName . '.mp4',
        );
    }
}
