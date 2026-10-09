<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadVideoRequest;
use App\Http\Resources\VideoResource;
use App\Services\VideoUploadService;
use Illuminate\Http\JsonResponse;

class VideoUploadController extends Controller
{
    public function __construct(private readonly VideoUploadService $uploads) {}

    /** 動画ファイルのアップロード POST /api/videos/upload */
    public function store(UploadVideoRequest $request): JsonResponse
    {
        $video = $this->uploads->store(
            $request->user(),
            $request->title,
            $request->file('file'),
            $request->team_id,
        );

        return (new VideoResource($video))->response()->setStatusCode(201);
    }
}
