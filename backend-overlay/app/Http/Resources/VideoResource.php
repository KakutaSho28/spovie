<?php

namespace App\Http\Resources;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class VideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'youtube_video_id' => $this->youtube_video_id,
            'file_url' => $this->type === Video::TYPE_UPLOAD && $this->file_path
                ? Storage::disk(config('filesystems.default'))->url($this->file_path)
                : null,
            'title' => $this->title,
            'team' => $this->team_id ? [
                'id' => $this->team_id,
                'name' => $this->whenLoaded('team', fn () => $this->team->name),
            ] : null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
