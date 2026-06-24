<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShareLinkRequest;
use App\Models\Annotation;
use App\Models\ShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ShareController extends Controller
{
    /**
     * SHARE-01 共有リンク発行
     */
    public function store(StoreShareLinkRequest $request, Annotation $annotation): JsonResponse
    {
        if ($annotation->video->user_id !== $request->user()->id) {
            return response()->json(['message' => 'この操作は許可されていません'], 403);
        }

        $shareLink = $annotation->shareLinks()->create([
            'token' => Str::random(64),
            'expires_at' => $request->expires_at,
        ]);

        return response()->json([
            'data' => [
                'token' => $shareLink->token,
                'share_url' => config('app.frontend_url', 'http://localhost') . '/share/' . $shareLink->token,
                'expires_at' => $shareLink->expires_at?->toIso8601String(),
                'created_at' => $shareLink->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * SHARE-02 共有リンク取得（認証不要）
     */
    public function show(string $token): JsonResponse
    {
        $shareLink = ShareLink::where('token', $token)->first();

        if (! $shareLink) {
            return response()->json(['message' => '共有リンクが見つかりません'], 404);
        }

        if ($shareLink->isExpired()) {
            return response()->json(['message' => 'この共有リンクは有効期限が切れています'], 410);
        }

        $annotation = $shareLink->annotation;
        $video = $annotation->video;

        return response()->json([
            'data' => [
                'annotation' => [
                    'id' => $annotation->id,
                    'start_seconds' => $annotation->start_seconds,
                    'end_seconds' => $annotation->end_seconds,
                    'canvas_data' => $annotation->canvas_data,
                    'comment' => $annotation->comment,
                ],
                'video' => [
                    'youtube_video_id' => $video->youtube_video_id,
                    'title' => $video->title,
                ],
                'expires_at' => $shareLink->expires_at?->toIso8601String(),
            ],
        ]);
    }
}
