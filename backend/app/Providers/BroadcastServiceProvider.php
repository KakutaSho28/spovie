<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // API は /api 配下のみ公開する構成のため、認証エンドポイントも /api/broadcasting/auth に置く。
        // 認証は Bearer トークン（Sanctum）。Echo の authorizer から呼ばれる。
        Broadcast::routes(['prefix' => 'api', 'middleware' => ['auth:sanctum']]);

        require base_path('routes/channels.php');
    }
}
