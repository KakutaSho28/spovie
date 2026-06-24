<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadVideoRequest;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class VideoUploadController extends Controller
{
    /**
     * 動画ファイルのアップロード
     * POST /api/videos/upload
     */
    public function store(UploadVideoRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $path = $file->storeAs(
            'videos',
            Str::uuid() . '.mp4',
            'public',
        );

        $video = $request->user()->videos()->create([
            'type' => Video::TYPE_UPLOAD,
            'file_path' => $path,
            'title' => $request->title,
        ]);

        return (new VideoResource($video))
            ->response()
            ->setStatusCode(201);
    }
}
