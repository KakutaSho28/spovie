<?php

/*
| 公開デモ用のシードデータ（php artisan db:seed）の設定。
| env() はここでだけ読む（本番の config:cache 後は、アプリコードから env() を読むと null になる）。
*/
return [

    // デモアカウントのパスワード。公開デモなので誰でも知る前提の値。本番で変えたい場合は環境変数で上書きする
    'password' => env('DEMO_PASSWORD', 'spovie-demo-1234'),

    // デモ用の YouTube 動画。Blender Foundation 公式の CC BY ライセンスの短編映画（公式チャンネル）。
    // 動画がなくなった・差し替えたい場合は環境変数で変更する
    'youtube_ids' => [
        env('DEMO_YOUTUBE_ID_1', 'aqz-KE-bpKQ'), // Big Buck Bunny
        env('DEMO_YOUTUBE_ID_2', 'eRsGyueVLvQ'), // Sintel
    ],

];
