<?php

use App\Models\Annotation;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// 動画（アノテーション一覧）: 動画を閲覧できるユーザー（投稿者 / チームメンバー）のみ
Broadcast::channel('video.{videoId}', function (User $user, int $videoId) {
    $video = Video::find($videoId);

    return $video !== null && $user->can('view', $video);
});

// アノテーション（コメント）: 所属する動画を閲覧できるユーザーのみ
Broadcast::channel('annotation.{annotationId}', function (User $user, int $annotationId) {
    $annotation = Annotation::with('video')->find($annotationId);

    return $annotation !== null && $user->can('view', $annotation);
});
