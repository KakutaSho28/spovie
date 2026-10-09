<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 共有ページ（認証不要）の表示用。チーム情報などは含めない。
 */
class SharedAnnotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $annotation = $this->annotation;
        $video = $annotation->video;

        return [
            'annotation' => [
                'id' => $annotation->id,
                'start_seconds' => $annotation->start_seconds,
                'end_seconds' => $annotation->end_seconds,
                'canvas_data' => $annotation->canvas_data,
                'comment' => $annotation->comment,
            ],
            'video' => [
                'type' => $video->type,
                'youtube_video_id' => $video->youtube_video_id,
                'file_url' => $video->fileUrl(),
                'title' => $video->title,
            ],
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
