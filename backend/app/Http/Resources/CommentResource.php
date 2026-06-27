<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'is_own' => $request->user()?->id === $this->user_id,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
