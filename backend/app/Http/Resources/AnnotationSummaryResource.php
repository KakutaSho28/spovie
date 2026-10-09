<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * リアルタイム通知用の軽量表現。
 * Pusher のメッセージ上限（約10KB）を超えないよう canvas_data は含めない（クライアントが一覧を再取得する）。
 */
class AnnotationSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'video_id' => $this->video_id,
            'start_seconds' => $this->start_seconds,
            'end_seconds' => $this->end_seconds,
            'comment' => $this->comment,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
