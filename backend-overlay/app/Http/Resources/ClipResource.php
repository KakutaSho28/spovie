<?php

namespace App\Http\Resources;

use App\Models\Clip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'video_id' => $this->video_id,
            'annotation_id' => $this->annotation_id,
            'title' => $this->title,
            'start_seconds' => $this->start_seconds,
            'end_seconds' => $this->end_seconds,
            'status' => $this->status,
            'download_url' => $this->status === Clip::STATUS_DONE
                ? url("/api/clips/{$this->id}/download")
                : null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
