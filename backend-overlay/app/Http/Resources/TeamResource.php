<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $frontendUrl = rtrim(env('APP_FRONTEND_URL', config('app.url')), '/');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'invite_token' => $this->invite_token,
            'invite_url' => "{$frontendUrl}/teams/join/{$this->invite_token}",
            'owner' => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ],
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
            ])->values()),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
