<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'video_id' => $this->video_id,
            'start_seconds' => $this->start_seconds,
            'end_seconds' => $this->end_seconds,
            'canvas_data' => $this->canvas_data,
            'comment' => $this->comment,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
