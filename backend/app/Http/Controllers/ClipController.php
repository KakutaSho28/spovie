<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClipRequest;
use App\Http\Resources\ClipResource;
use App\Models\Clip;
use App\Models\Video;
use App\Services\ClipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClipController extends Controller
{
    public function __construct(private readonly ClipService $clips) {}

    /** 切り抜きジョブの作成 POST /api/clips */
    public function store(StoreClipRequest $request): JsonResponse
    {
        $video = Video::findOrFail($request->video_id);
        $this->authorize('view', $video);

        $clip = $this->clips->create(
            $video,
            $request->annotation_id,
            $request->title,
            $request->start_seconds,
            $request->end_seconds,
        );

        return (new ClipResource($clip))->response()->setStatusCode(202);
    }

    /** 切り抜き状態の取得（ポーリング用） GET /api/clips/{clip} */
    public function show(Clip $clip): ClipResource
    {
        $this->authorize('view', $clip);

        return new ClipResource($clip);
    }

    /** 切り抜き動画のダウンロード（認証不要 = LINE共有用、トークン必須） */
    public function download(Clip $clip, string $token): StreamedResponse
    {
        $fileName = $this->clips->downloadName($clip, $token);

        return Storage::disk(config('filesystems.default'))->download($clip->file_path, $fileName);
    }
}
