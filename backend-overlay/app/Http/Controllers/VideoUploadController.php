<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadVideoRequest;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoUploadController extends Controller
{
    /**
     * 動画ファイルのアップロード
     * POST /api/videos/upload
     */
    public function store(UploadVideoRequest $request): JsonResponse
    {
        if ($request->team_id && ! $request->user()->teams()->where('teams.id', $request->team_id)->exists()) {
            return response()->json(['message' => 'このチームに動画を追加できません'], 403);
        }

        $file = $request->file('file');
        $path = Storage::disk(config('filesystems.default'))->putFileAs(
            'videos',
            $file,
            Str::uuid() . '.mp4',
        );

        $video = $request->user()->videos()->create([
            'team_id' => $request->team_id,
            'type' => Video::TYPE_UPLOAD,
            'file_path' => $path,
            'title' => $request->title,
        ]);

        return (new VideoResource($video))
            ->response()
            ->setStatusCode(201);
    }
}
