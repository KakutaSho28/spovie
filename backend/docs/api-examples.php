<?php

/**
 * API ドキュメント（docs/api-endpoints.md）の説明とリクエスト/レスポンス例。
 *
 * ルート・パス・認証・バリデーションルールは `php artisan route:list --json` と FormRequest から自動生成されるので、
 * ここには「人が書く部分」だけを置く。ルートを追加/削除すると ApiDocsTest が失敗するので、
 * このファイルを更新して `php artisan docs:api` で docs/api-endpoints.md を再生成する。
 *
 * キーは「<HTTPメソッド> /<URI>」（HEAD を除く。複数メソッドは | で連結）。
 */

$video = [
    'id' => 12,
    'type' => 'youtube',
    'youtube_video_id' => 'dQw4w9WgXcQ',
    'file_url' => null,
    'title' => '準決勝 前半',
    'team' => ['id' => 3, 'name' => 'FC スポビー'],
    'created_at' => '2026-10-10T10:00:00+09:00',
];

$canvas = [
    'canvas_width' => 1280,
    'canvas_height' => 720,
    'objects' => [
        ['type' => 'Circle', 'left' => 400, 'top' => 220, 'radius' => 40, 'stroke' => '#ff3b30', 'strokeWidth' => 4, 'fill' => ''],
    ],
];

$annotation = [
    'id' => 45,
    'video_id' => 12,
    'start_seconds' => 83,
    'end_seconds' => 91,
    'canvas_data' => $canvas,
    'comment' => '3番のヘルプが0.5秒遅い',
    'comments_count' => 2,
    'created_at' => '2026-10-10T10:30:00+09:00',
];

$comment = [
    'id' => 7,
    'body' => 'ナイスカット',
    'user' => ['id' => 2, 'name' => 'ボブ'],
    'is_own' => false,
    'created_at' => '2026-10-10T11:00:00+09:00',
];

$team = [
    'id' => 3,
    'name' => 'FC スポビー',
    'invite_token' => 'Zk3...（64文字のランダム文字列）',
    'invite_url' => 'https://spovie.example.vercel.app/teams/join/Zk3...',
    'owner' => ['id' => 1, 'name' => 'アリス'],
    'members' => [
        ['id' => 1, 'name' => 'アリス', 'email' => 'alice@example.com', 'role' => 'owner'],
        ['id' => 2, 'name' => 'ボブ', 'email' => 'bob@example.com', 'role' => 'member'],
    ],
    'created_at' => '2026-10-09T09:00:00+09:00',
];

$clip = [
    'id' => 5,
    'video_id' => 13,
    'annotation_id' => null,
    'title' => 'ゴールシーン',
    'start_seconds' => 5,
    'end_seconds' => 15,
    'status' => 'processing',
    'download_url' => null,
    'created_at' => '2026-10-10T12:00:00+09:00',
];

$pageLinks = ['first' => 'https://api.example.com/api/videos?page=1', 'last' => 'https://api.example.com/api/videos?page=3', 'prev' => null, 'next' => 'https://api.example.com/api/videos?page=2'];
$pageMeta = [
    'current_page' => 1,
    'from' => 1,
    'last_page' => 3,
    'links' => [['url' => null, 'label' => '&laquo; Previous', 'active' => false], ['url' => 'https://api.example.com/api/videos?page=1', 'label' => '1', 'active' => true]],
    'path' => 'https://api.example.com/api/videos',
    'per_page' => 20,
    'to' => 20,
    'total' => 45,
];
$auth = ['user' => ['id' => 1, 'name' => 'アリス', 'email' => 'alice@example.com'], 'token' => '1|abcdef...（Sanctum のトークン）'];

return [

    // ===== 認証 =====
    'POST /api/auth/register' => [
        'group' => '認証',
        'summary' => 'ユーザー登録',
        'description' => '登録と同時にトークンを発行する。以降は `Authorization: Bearer <token>` を付ける。',
        'request' => ['name' => 'アリス', 'email' => 'alice@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'],
        'responses' => [201 => ['description' => '登録成功', 'example' => ['data' => $auth]], 422 => ['description' => '入力不正（メール重複など）']],
    ],
    'POST /api/auth/login' => [
        'group' => '認証',
        'summary' => 'ログイン',
        'description' => '既存のトークンはすべて失効し、新しいトークンを発行する（多重ログイン防止）。',
        'request' => ['email' => 'alice@example.com', 'password' => 'password123'],
        'responses' => [200 => ['description' => 'ログイン成功', 'example' => ['data' => $auth]], 401 => ['description' => 'メールアドレスまたはパスワードが違う', 'example' => ['message' => 'メールアドレスまたはパスワードが正しくありません']]],
    ],
    'POST /api/auth/logout' => [
        'group' => '認証',
        'summary' => 'ログアウト',
        'description' => '現在のトークンを失効させる。',
        'responses' => [200 => ['description' => 'ログアウト成功', 'example' => ['message' => 'ログアウトしました']]],
    ],

    // ===== 動画 =====
    'GET /api/videos' => [
        'group' => '動画',
        'summary' => '動画一覧',
        'description' => '自分の個人動画と所属チームの動画を、新しい順（`created_at` 降順、同時刻は `id` 降順）に 1 ページ 20 件で返す。`team_id` を指定するとそのチームの動画のみ（所属外のチームは 403）、`scope=personal` で個人動画のみ。',
        'query' => ['page' => 'ページ番号（1〜）', 'per_page' => '1 ページの件数（既定 20、最大 100）', 'scope' => '`all`（既定）/ `personal`', 'team_id' => 'チームで絞り込み'],
        'responses' => [200 => ['description' => '動画の配列とページ情報', 'example' => ['data' => [$video], 'links' => $pageLinks, 'meta' => $pageMeta]], 403 => ['description' => '所属していないチームの `team_id`']],
    ],
    'POST /api/videos' => [
        'group' => '動画',
        'summary' => 'YouTube 動画の登録',
        'description' => '`youtube_url` は `watch?v=` / `youtu.be/` / `embed/` 形式に対応。`team_id` を指定するとチーム動画になる（所属チームのみ）。',
        'request' => ['title' => '準決勝 前半', 'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'team_id' => 3],
        'responses' => [201 => ['description' => '登録成功', 'example' => ['data' => $video]], 403 => ['description' => '所属していないチーム', 'example' => ['message' => 'このチームに動画を追加できません']]],
    ],
    'POST /api/videos/upload' => [
        'group' => '動画',
        'summary' => '動画ファイル（mp4）のアップロード',
        'description' => '`multipart/form-data`。サイズ上限は環境変数 `UPLOAD_MAX_MB`（既定 200MB）。保存先は `FILESYSTEM_DISK`（本番は S3 互換ストレージ）。',
        'request' => ['title' => '練習試合', 'file' => '(mp4 ファイル)', 'team_id' => null],
        'responses' => [201 => ['description' => 'アップロード成功', 'example' => ['data' => ['id' => 13, 'type' => 'upload', 'youtube_video_id' => null, 'file_url' => 'https://.../videos/xxxx.mp4?X-Amz-Signature=...（一時署名URL）', 'title' => '練習試合', 'team' => null, 'created_at' => '2026-10-10T10:00:00+09:00']]], 413 => ['description' => 'リクエストサイズ超過（nginx）'], 422 => ['description' => 'mp4 でない / サイズ超過']],
    ],
    'GET /api/videos/{video}' => [
        'group' => '動画',
        'summary' => '動画の詳細',
        'description' => '投稿者、またはチーム動画ならチームメンバーのみ。',
        'responses' => [200 => ['description' => '動画', 'example' => ['data' => $video]], 403 => ['description' => '閲覧権限なし', 'example' => ['message' => 'この操作は許可されていません']], 404 => ['description' => '存在しない']],
    ],
    'DELETE /api/videos/{video}' => [
        'group' => '動画',
        'summary' => '動画の削除',
        'description' => '投稿者、またはチーム動画ならチームオーナーのみ。アノテーション・コメント・クリップも削除される。',
        'responses' => [200 => ['description' => '削除成功', 'example' => ['message' => '動画を削除しました']], 403 => ['description' => '権限なし']],
    ],

    // ===== アノテーション =====
    'GET /api/videos/{video}/annotations' => [
        'group' => 'アノテーション',
        'summary' => 'アノテーション一覧',
        'description' => '新しい順。`comments_count` はコメント数。',
        'responses' => [200 => ['description' => 'アノテーションの配列', 'example' => ['data' => [$annotation]]], 403 => ['description' => '動画の閲覧権限なし']],
    ],
    'POST /api/videos/{video}/annotations' => [
        'group' => 'アノテーション',
        'summary' => 'アノテーションの保存',
        'description' => '`canvas_data` は Fabric.js の `toJSON()` の `objects`（保存時のキャンバスの絶対座標）と、保存時のサイズ `canvas_width` / `canvas_height`。表示時は「現在のサイズ ÷ 保存時のサイズ」で拡大縮小して再現する。保存後、同じ動画の一覧を見ている人へ `annotation.created` を配信する。',
        'request' => ['start_seconds' => 83, 'end_seconds' => 91, 'canvas_data' => $canvas, 'comment' => '3番のヘルプが0.5秒遅い'],
        'responses' => [201 => ['description' => '保存成功', 'example' => ['data' => array_diff_key($annotation, ['comments_count' => 0])]], 403 => ['description' => '動画の閲覧権限なし']],
    ],
    'DELETE /api/annotations/{annotation}' => [
        'group' => 'アノテーション',
        'summary' => 'アノテーションの削除',
        'description' => '動画を閲覧できるユーザーなら削除できる。',
        'responses' => [200 => ['description' => '削除成功', 'example' => ['message' => 'アノテーションを削除しました']], 403 => ['description' => '権限なし']],
    ],

    // ===== 共有リンク =====
    'POST /api/annotations/{annotation}/share' => [
        'group' => '共有リンク',
        'summary' => '共有リンクの発行',
        'description' => '`expires_at` を省略すると無期限。`share_url` はフロントエンドの `/share/{token}`。',
        'request' => ['expires_at' => null],
        'responses' => [201 => ['description' => '発行成功', 'example' => ['data' => ['token' => '64文字のランダム文字列', 'share_url' => 'https://spovie.example.vercel.app/share/64文字...', 'expires_at' => null, 'created_at' => '2026-10-10T10:40:00+09:00']]], 403 => ['description' => '権限なし']],
    ],
    'GET /api/share/{token}' => [
        'group' => '共有リンク',
        'summary' => '共有リンクの閲覧（認証不要）',
        'description' => '未ログインで見られる。チーム名など内部情報は含めない。アップロード動画は `file_url`（一時署名URL）で再生する。',
        'responses' => [200 => ['description' => '共有内容', 'example' => ['data' => ['annotation' => ['id' => 45, 'start_seconds' => 83, 'end_seconds' => 91, 'canvas_data' => $canvas, 'comment' => '3番のヘルプが0.5秒遅い'], 'video' => ['type' => 'youtube', 'youtube_video_id' => 'dQw4w9WgXcQ', 'file_url' => null, 'title' => '準決勝 前半'], 'expires_at' => null]]], 404 => ['description' => 'リンクが存在しない', 'example' => ['message' => '共有リンクが見つかりません']], 410 => ['description' => '有効期限切れ', 'example' => ['message' => 'この共有リンクは有効期限が切れています']]],
    ],

    // ===== コメント =====
    'GET /api/annotations/{annotation}/comments' => [
        'group' => 'コメント',
        'summary' => 'コメント一覧',
        'description' => '古い順。`is_own` は自分のコメントかどうか。',
        'responses' => [200 => ['description' => 'コメントの配列', 'example' => ['data' => [$comment]]], 403 => ['description' => '権限なし']],
    ],
    'POST /api/annotations/{annotation}/comments' => [
        'group' => 'コメント',
        'summary' => 'コメントの投稿',
        'description' => '投稿後、同じアノテーションを開いている他の人へ `comment.created` を配信する（投稿者本人には送らない）。',
        'request' => ['body' => 'ナイスカット'],
        'responses' => [201 => ['description' => '投稿成功', 'example' => ['data' => array_merge($comment, ['is_own' => true])]], 403 => ['description' => '権限なし']],
    ],
    'DELETE /api/comments/{comment}' => [
        'group' => 'コメント',
        'summary' => 'コメントの削除',
        'description' => '投稿者本人のみ。削除後 `comment.deleted` を配信する。',
        'responses' => [200 => ['description' => '削除成功', 'example' => ['message' => 'コメントを削除しました']], 403 => ['description' => '投稿者ではない']],
    ],

    // ===== チーム =====
    'GET /api/teams' => [
        'group' => 'チーム',
        'summary' => '所属チーム一覧',
        'responses' => [200 => ['description' => 'チームの配列', 'example' => ['data' => [$team]]]],
    ],
    'POST /api/teams' => [
        'group' => 'チーム',
        'summary' => 'チームの作成',
        'description' => '作成者がオーナー兼最初のメンバーになる。招待トークン（64文字）が自動生成される。',
        'request' => ['name' => 'FC スポビー'],
        'responses' => [201 => ['description' => '作成成功', 'example' => ['data' => $team]]],
    ],
    'GET /api/teams/invite/{token}' => [
        'group' => 'チーム',
        'summary' => '招待トークンからチームを確認',
        'description' => '参加前の確認用。トークンを知っている人にだけ見せる想定。',
        'responses' => [200 => ['description' => 'チーム', 'example' => ['data' => $team]], 404 => ['description' => 'トークンが無効']],
    ],
    'POST /api/teams/join' => [
        'group' => 'チーム',
        'summary' => '招待トークンでチームに参加',
        'description' => '冪等。既にメンバーなら何も変えずに 200 を返す。',
        'request' => ['invite_token' => 'Zk3...（64文字）'],
        'responses' => [200 => ['description' => '参加後のチーム', 'example' => ['data' => $team]], 404 => ['description' => '招待リンクが無効', 'example' => ['message' => '招待リンクが無効です']]],
    ],
    'GET /api/teams/{team}' => [
        'group' => 'チーム',
        'summary' => 'チーム詳細（メンバー一覧付き）',
        'description' => 'メンバーのみ。',
        'responses' => [200 => ['description' => 'チーム', 'example' => ['data' => $team]], 403 => ['description' => 'メンバーではない']],
    ],
    'DELETE /api/teams/{team}' => [
        'group' => 'チーム',
        'summary' => 'チームの削除',
        'description' => 'オーナーのみ。チームの動画は投稿者の個人動画に戻る。',
        'responses' => [200 => ['description' => '削除成功', 'example' => ['message' => 'チームを削除しました']], 403 => ['description' => 'オーナーではない']],
    ],
    'DELETE /api/teams/{team}/members/{user}' => [
        'group' => 'チーム',
        'summary' => 'メンバーの削除 / 脱退',
        'description' => 'オーナーは任意のメンバーを外せる。メンバーは自分自身のみ（脱退）。オーナーは外せず、脱退もできない（422）。',
        'responses' => [200 => ['description' => '成功', 'example' => ['message' => 'メンバーを削除しました']], 403 => ['description' => '他のメンバーを外そうとした（オーナー以外）'], 404 => ['description' => 'そのチームのメンバーではない'], 422 => ['description' => 'オーナーは削除・脱退できない', 'example' => ['message' => 'オーナーは削除・脱退できません']]],
    ],
    'GET /api/teams/{team}/videos' => [
        'group' => 'チーム',
        'summary' => 'チームの動画一覧',
        'description' => 'メンバーのみ。1 ページ 20 件。',
        'query' => ['page' => 'ページ番号', 'per_page' => '件数'],
        'responses' => [200 => ['description' => '動画の配列とページ情報', 'example' => ['data' => [$video], 'links' => $pageLinks, 'meta' => $pageMeta]], 403 => ['description' => 'メンバーではない']],
    ],

    // ===== 切り抜き =====
    'POST /api/clips' => [
        'group' => '切り抜き',
        'summary' => '切り抜きジョブの作成',
        'description' => '**アップロード動画のみ**（YouTube 動画は著作権ポリシーにより 422）。FFmpeg（`-c copy`）でキュー処理される。状態は `GET /api/clips/{clip}` をポーリングして確認する。',
        'request' => ['video_id' => 13, 'annotation_id' => null, 'title' => 'ゴールシーン', 'start_seconds' => 5, 'end_seconds' => 15],
        'responses' => [202 => ['description' => '受付（処理中）', 'example' => ['data' => $clip]], 403 => ['description' => '動画の閲覧権限なし'], 422 => ['description' => 'YouTube 動画は切り抜き不可', 'example' => ['message' => 'YouTube動画は切り抜き保存できません。直接アップロードした動画のみ対応しています。']]],
    ],
    'GET /api/clips/{clip}' => [
        'group' => '切り抜き',
        'summary' => '切り抜きの状態',
        'description' => '`status` は `processing` / `done` / `error`。`done` のときだけ `download_url`（推測できないトークン付き）が入る。',
        'responses' => [200 => ['description' => 'クリップ', 'example' => ['data' => array_merge($clip, ['status' => 'done', 'download_url' => 'https://api.example.com/api/clips/5/download/40文字のトークン'])]], 403 => ['description' => '権限なし']],
    ],
    'GET /api/clips/{clip}/download/{token}' => [
        'group' => '切り抜き',
        'summary' => '切り抜き動画のダウンロード（認証不要）',
        'description' => 'LINE などで共有できるよう認証は不要だが、URL に `download_token`（40文字）が必要。トークン不一致・未完了・ファイル無しは 404。S3 互換ストレージでは一時署名URLへ 302 リダイレクトする。',
        'responses' => [200 => ['description' => 'mp4 ファイル（ローカルディスクの場合）'], 302 => ['description' => '署名付きURLへリダイレクト（S3 互換）'], 404 => ['description' => 'クリップが見つからない']],
    ],

    // ===== リアルタイム・運用 =====
    'GET|POST /api/broadcasting/auth' => [
        'group' => 'リアルタイム・運用',
        'summary' => 'WebSocket（Pusher）の private チャンネル認証',
        'description' => 'Laravel Echo の `authorizer` から呼ばれる。Bearer 認証。`private-annotation.{id}` / `private-video.{id}` は、その動画を閲覧できるユーザー（投稿者・チームメンバー）のみ許可。通知の仕様は「リアルタイムイベント」を参照。',
        'request' => ['socket_id' => '1234.5678', 'channel_name' => 'private-annotation.45'],
        'responses' => [200 => ['description' => '許可', 'example' => ['auth' => 'pusher-key:署名']], 403 => ['description' => '購読権限なし'], 401 => ['description' => '未認証']],
    ],
    'GET /api/health' => [
        'group' => 'リアルタイム・運用',
        'summary' => 'ヘルスチェック（認証不要）',
        'description' => 'DB に接続できれば 200、できなければ 503。稼働監視と Railway の healthcheck に使う。',
        'responses' => [200 => ['description' => '正常', 'example' => ['data' => ['status' => 'ok', 'database' => 'ok']]], 503 => ['description' => 'DB に接続できない', 'example' => ['data' => ['status' => 'error', 'database' => 'error']]]],
    ],
];
