<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ログイン / 登録レスポンス。リソースには ['user' => User, 'token' => string] を渡す。
 */
class AuthTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->resource['user'];

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $this->resource['token'],
        ];
    }
}
