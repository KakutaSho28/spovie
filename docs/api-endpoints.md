# API 仕様

> このファイルは自動生成です。直接編集せず、`backend/docs/api-examples.php` を更新して `cd backend && php artisan docs:api` を実行してください。
> ルート・パス・認証・バリデーションルールは `php artisan route:list --json` と FormRequest から取得しており、`ApiDocsTest` がコードとのずれを検知します。

## 共通仕様

- ベース URL: `https://<railway-domain>/api`（ローカル: `http://localhost/api`）
- 形式: JSON（`Accept: application/json`）。アップロードのみ `multipart/form-data`
- 認証: Laravel Sanctum の Bearer トークン。`POST /api/auth/register` または `POST /api/auth/login` で取得し、`Authorization: Bearer <token>` を付ける。「認証: 必要」のエンドポイントで未指定・無効なら 401
- レスポンスの形式: 成功は `{ "data": ... }`、メッセージのみは `{ "message": "..." }`。一覧（ページング）は `{ "data": [...], "meta": {...}, "links": {...} }`
- 日時: ISO 8601（例 `2026-10-10T10:00:00+09:00`）
- ページング: 1 ページ 20 件。`?page=` で指定

### 共通エラー

| ステータス | 意味 | 形式 |
|---|---|---|
| 401 | 未認証 | `{ "message": "Unauthenticated." }` |
| 403 | 権限なし | `{ "message": "この操作は許可されていません" }` |
| 404 | 対象が存在しない | `{ "message": "..." }` |
| 422 | 入力エラー | `{ "message": "...", "errors": { "<field>": ["..."] } }` |
| 429 | リクエスト過多（スロットリング） | `{ "message": "Too Many Attempts." }` |

### 権限の考え方

| 対象 | 閲覧・アノテーション・コメント・共有・切り抜き | 削除 |
|---|---|---|
| 個人動画 | 投稿者のみ | 投稿者のみ |
| チーム動画 | チームメンバー全員 | 投稿者、またはチームオーナー |
| コメント | 動画を閲覧できる人 | 投稿者本人のみ |
| チーム | メンバー全員が閲覧 | オーナーのみ（メンバーの削除はオーナー、脱退は本人） |

## エンドポイント一覧

### 認証

| メソッド | パス | 認証 | 概要 |
|---|---|---|---|
| POST | `/api/auth/register` | 不要 | ユーザー登録 |
| POST | `/api/auth/login` | 不要 | ログイン |
| POST | `/api/auth/logout` | 必要 | ログアウト |

### 動画

| メソッド | パス | 認証 | 概要 |
|---|---|---|---|
| GET | `/api/videos` | 必要 | 動画一覧 |
| POST | `/api/videos` | 必要 | YouTube 動画の登録 |
| POST | `/api/videos/upload` | 必要 | 動画ファイル（mp4）のアップロード |
| GET | `/api/videos/{video}` | 必要 | 動画の詳細 |
| DELETE | `/api/videos/{video}` | 必要 | 動画の削除 |

### アノテーション

| メソッド | パス | 認証 | 概要 |
|---|---|---|---|
| GET | `/api/videos/{video}/annotations` | 必要 | アノテーション一覧 |
| POST | `/api/videos/{video}/annotations` | 必要 | アノテーションの保存 |
| DELETE | `/api/annotations/{annotation}` | 必要 | アノテーションの削除 |

### 共有リンク

| メソッド | パス | 認証 | 概要 |
|---|---|---|---|
| POST | `/api/annotations/{annotation}/share` | 必要 | 共有リンクの発行 |
| GET | `/api/share/{token}` | 不要 | 共有リンクの閲覧（認証不要） |

### コメント

| メソッド | パス | 認証 | 概要 |
|---|---|---|---|
| GET | `/api/annotations/{annotation}/comments` | 必要 | コメント一覧 |
| POST | `/api/annotations/{annotation}/comments` | 必要 | コメントの投稿 |
| DELETE | `/api/comments/{comment}` | 必要 | コメントの削除 |

### チーム

| メソッド | パス | 認証 | 概要 |
|---|---|---|---|
| GET | `/api/teams` | 必要 | 所属チーム一覧 |
| POST | `/api/teams` | 必要 | チームの作成 |
| GET | `/api/teams/invite/{token}` | 必要 | 招待トークンからチームを確認 |
| POST | `/api/teams/join` | 必要 | 招待トークンでチームに参加 |
| GET | `/api/teams/{team}` | 必要 | チーム詳細（メンバー一覧付き） |
| DELETE | `/api/teams/{team}` | 必要 | チームの削除 |
| DELETE | `/api/teams/{team}/members/{user}` | 必要 | メンバーの削除 / 脱退 |
| GET | `/api/teams/{team}/videos` | 必要 | チームの動画一覧 |

### 切り抜き

| メソッド | パス | 認証 | 概要 |
|---|---|---|---|
| POST | `/api/clips` | 必要 | 切り抜きジョブの作成 |
| GET | `/api/clips/{clip}` | 必要 | 切り抜きの状態 |
| GET | `/api/clips/{clip}/download/{token}` | 不要 | 切り抜き動画のダウンロード（認証不要） |

### リアルタイム・運用

| メソッド | パス | 認証 | 概要 |
|---|---|---|---|
| GET\|POST | `/api/broadcasting/auth` | 必要 | WebSocket（Pusher）の private チャンネル認証 |
| GET | `/api/health` | 不要 | ヘルスチェック（認証不要） |

---

## POST `/api/auth/register`

**ユーザー登録**

- 実装: `App\Http\Controllers\AuthController@register`
- 認証: 不要

登録と同時にトークンを発行する。以降は `Authorization: Bearer <token>` を付ける。

**リクエストのバリデーション**（FormRequest から自動取得）

| フィールド | ルール |
|---|---|
| `name` | `required` `string` `max:100` |
| `email` | `required` `email` `unique:users,email` |
| `password` | `required` `string` `min:8` `confirmed` |

**リクエスト例**

```json
{
    "name": "アリス",
    "email": "alice@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**レスポンス**

- `201` 登録成功

```json
{
    "data": {
        "user": {
            "id": 1,
            "name": "アリス",
            "email": "alice@example.com"
        },
        "token": "1|abcdef...（Sanctum のトークン）"
    }
}
```

- `422` 入力不正（メール重複など）

---

## POST `/api/auth/login`

**ログイン**

- 実装: `App\Http\Controllers\AuthController@login`
- 認証: 不要

既存のトークンはすべて失効し、新しいトークンを発行する（多重ログイン防止）。

**リクエストのバリデーション**（FormRequest から自動取得）

| フィールド | ルール |
|---|---|
| `email` | `required` `email` |
| `password` | `required` `string` `min:8` |

**リクエスト例**

```json
{
    "email": "alice@example.com",
    "password": "password123"
}
```

**レスポンス**

- `200` ログイン成功

```json
{
    "data": {
        "user": {
            "id": 1,
            "name": "アリス",
            "email": "alice@example.com"
        },
        "token": "1|abcdef...（Sanctum のトークン）"
    }
}
```

- `401` メールアドレスまたはパスワードが違う

```json
{
    "message": "メールアドレスまたはパスワードが正しくありません"
}
```


---

## POST `/api/auth/logout`

**ログアウト**

- 実装: `App\Http\Controllers\AuthController@logout`
- 認証: 必要（Bearer トークン）

現在のトークンを失効させる。

**レスポンス**

- `200` ログアウト成功

```json
{
    "message": "ログアウトしました"
}
```


---

## GET `/api/videos`

**動画一覧**

- 実装: `App\Http\Controllers\VideoController@index`
- 認証: 必要（Bearer トークン）

自分の個人動画と所属チームの動画を、新しい順（`created_at` 降順、同時刻は `id` 降順）に 1 ページ 20 件で返す。`team_id` を指定するとそのチームの動画のみ（所属外のチームは 403）、`scope=personal` で個人動画のみ。

**クエリパラメータ**

| 名前 | 説明 |
|---|---|
| `page` | ページ番号（1〜） |
| `per_page` | 1 ページの件数（既定 20、最大 100） |
| `scope` | `all`（既定）/ `personal` |
| `team_id` | チームで絞り込み |

**リクエストのバリデーション**（FormRequest から自動取得）

| フィールド | ルール |
|---|---|
| `scope` | `nullable` `in:personal,all` |
| `team_id` | `nullable` `integer` |
| `page` | `nullable` `integer` `min:1` |
| `per_page` | `nullable` `integer` `min:1` `max:100` |

**レスポンス**

- `200` 動画の配列とページ情報

```json
{
    "data": [
        {
            "id": 12,
            "type": "youtube",
            "youtube_video_id": "dQw4w9WgXcQ",
            "file_url": null,
            "title": "準決勝 前半",
            "team": {
                "id": 3,
                "name": "FC スポビー"
            },
            "created_at": "2026-10-10T10:00:00+09:00"
        }
    ],
    "links": {
        "first": "https://api.example.com/api/videos?page=1",
        "last": "https://api.example.com/api/videos?page=3",
        "prev": null,
        "next": "https://api.example.com/api/videos?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 3,
        "links": [
            {
                "url": null,
                "label": "&laquo; Previous",
                "active": false
            },
            {
                "url": "https://api.example.com/api/videos?page=1",
                "label": "1",
                "active": true
            }
        ],
        "path": "https://api.example.com/api/videos",
        "per_page": 20,
        "to": 20,
        "total": 45
    }
}
```

- `403` 所属していないチームの `team_id`

---

## POST `/api/videos`

**YouTube 動画の登録**

- 実装: `App\Http\Controllers\VideoController@store`
- 認証: 必要（Bearer トークン）

`youtube_url` は `watch?v=` / `youtu.be/` / `embed/` 形式に対応。`team_id` を指定するとチーム動画になる（所属チームのみ）。

**リクエストのバリデーション**（FormRequest から自動取得）

| フィールド | ルール |
|---|---|
| `title` | `required` `string` `max:255` |
| `youtube_url` | `required` `url` `regex:/(?:v=\|youtu\.be\/\|embed\/)([a-zA-Z0-9_-]{11})/` |
| `team_id` | `nullable` `integer` `exists:teams,id` |

**リクエスト例**

```json
{
    "title": "準決勝 前半",
    "youtube_url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
    "team_id": 3
}
```

**レスポンス**

- `201` 登録成功

```json
{
    "data": {
        "id": 12,
        "type": "youtube",
        "youtube_video_id": "dQw4w9WgXcQ",
        "file_url": null,
        "title": "準決勝 前半",
        "team": {
            "id": 3,
            "name": "FC スポビー"
        },
        "created_at": "2026-10-10T10:00:00+09:00"
    }
}
```

- `403` 所属していないチーム

```json
{
    "message": "このチームに動画を追加できません"
}
```


---

## POST `/api/videos/upload`

**動画ファイル（mp4）のアップロード**

- 実装: `App\Http\Controllers\VideoUploadController@store`
- 認証: 必要（Bearer トークン）

`multipart/form-data`。サイズ上限は環境変数 `UPLOAD_MAX_MB`（既定 200MB）。保存先は `FILESYSTEM_DISK`（本番は S3 互換ストレージ）。

**リクエストのバリデーション**（FormRequest から自動取得）

| フィールド | ルール |
|---|---|
| `title` | `required` `string` `max:255` |
| `file` | `required` `file` `mimetypes:video/mp4` `max:204800` |
| `team_id` | `nullable` `integer` `exists:teams,id` |

**リクエスト例**

```json
{
    "title": "練習試合",
    "file": "(mp4 ファイル)",
    "team_id": null
}
```

**レスポンス**

- `201` アップロード成功

```json
{
    "data": {
        "id": 13,
        "type": "upload",
        "youtube_video_id": null,
        "file_url": "https://.../videos/xxxx.mp4?X-Amz-Signature=...（一時署名URL）",
        "title": "練習試合",
        "team": null,
        "created_at": "2026-10-10T10:00:00+09:00"
    }
}
```

- `413` リクエストサイズ超過（nginx）
- `422` mp4 でない / サイズ超過

---

## GET `/api/videos/{video}`

**動画の詳細**

- 実装: `App\Http\Controllers\VideoController@show`
- 認証: 必要（Bearer トークン）

投稿者、またはチーム動画ならチームメンバーのみ。

**パスパラメータ**: `video`

**レスポンス**

- `200` 動画

```json
{
    "data": {
        "id": 12,
        "type": "youtube",
        "youtube_video_id": "dQw4w9WgXcQ",
        "file_url": null,
        "title": "準決勝 前半",
        "team": {
            "id": 3,
            "name": "FC スポビー"
        },
        "created_at": "2026-10-10T10:00:00+09:00"
    }
}
```

- `403` 閲覧権限なし

```json
{
    "message": "この操作は許可されていません"
}
```

- `404` 存在しない

---

## DELETE `/api/videos/{video}`

**動画の削除**

- 実装: `App\Http\Controllers\VideoController@destroy`
- 認証: 必要（Bearer トークン）

投稿者、またはチーム動画ならチームオーナーのみ。アノテーション・コメント・クリップも削除される。

**パスパラメータ**: `video`

**レスポンス**

- `200` 削除成功

```json
{
    "message": "動画を削除しました"
}
```

- `403` 権限なし

---

## GET `/api/videos/{video}/annotations`

**アノテーション一覧**

- 実装: `App\Http\Controllers\AnnotationController@index`
- 認証: 必要（Bearer トークン）

新しい順。`comments_count` はコメント数。

**パスパラメータ**: `video`

**レスポンス**

- `200` アノテーションの配列

```json
{
    "data": [
        {
            "id": 45,
            "video_id": 12,
            "start_seconds": 83,
            "end_seconds": 91,
            "canvas_data": {
                "canvas_width": 1280,
                "canvas_height": 720,
                "objects": [
                    {
                        "type": "Circle",
                        "left": 400,
                        "top": 220,
                        "radius": 40,
                        "stroke": "#ff3b30",
                        "strokeWidth": 4,
                        "fill": ""
                    }
                ]
            },
            "comment": "3番のヘルプが0.5秒遅い",
            "comments_count": 2,
            "created_at": "2026-10-10T10:30:00+09:00"
        }
    ]
}
```

- `403` 動画の閲覧権限なし

---

## POST `/api/videos/{video}/annotations`

**アノテーションの保存**

- 実装: `App\Http\Controllers\AnnotationController@store`
- 認証: 必要（Bearer トークン）

`canvas_data` は Fabric.js の `toJSON()` の `objects`（保存時のキャンバスの絶対座標）と、保存時のサイズ `canvas_width` / `canvas_height`。表示時は「現在のサイズ ÷ 保存時のサイズ」で拡大縮小して再現する。保存後、同じ動画の一覧を見ている人へ `annotation.created` を配信する。

**パスパラメータ**: `video`

**リクエストのバリデーション**（FormRequest から自動取得）

| フィールド | ルール |
|---|---|
| `start_seconds` | `required` `integer` `min:0` |
| `end_seconds` | `required` `integer` `gt:start_seconds` |
| `canvas_data` | `required` `array` |
| `comment` | `nullable` `string` `max:1000` |

**リクエスト例**

```json
{
    "start_seconds": 83,
    "end_seconds": 91,
    "canvas_data": {
        "canvas_width": 1280,
        "canvas_height": 720,
        "objects": [
            {
                "type": "Circle",
                "left": 400,
                "top": 220,
                "radius": 40,
                "stroke": "#ff3b30",
                "strokeWidth": 4,
                "fill": ""
            }
        ]
    },
    "comment": "3番のヘルプが0.5秒遅い"
}
```

**レスポンス**

- `201` 保存成功

```json
{
    "data": {
        "id": 45,
        "video_id": 12,
        "start_seconds": 83,
        "end_seconds": 91,
        "canvas_data": {
            "canvas_width": 1280,
            "canvas_height": 720,
            "objects": [
                {
                    "type": "Circle",
                    "left": 400,
                    "top": 220,
                    "radius": 40,
                    "stroke": "#ff3b30",
                    "strokeWidth": 4,
                    "fill": ""
                }
            ]
        },
        "comment": "3番のヘルプが0.5秒遅い",
        "created_at": "2026-10-10T10:30:00+09:00"
    }
}
```

- `403` 動画の閲覧権限なし

---

## DELETE `/api/annotations/{annotation}`

**アノテーションの削除**

- 実装: `App\Http\Controllers\AnnotationController@destroy`
- 認証: 必要（Bearer トークン）

動画を閲覧できるユーザーなら削除できる。

**パスパラメータ**: `annotation`

**レスポンス**

- `200` 削除成功

```json
{
    "message": "アノテーションを削除しました"
}
```

- `403` 権限なし

---

## POST `/api/annotations/{annotation}/share`

**共有リンクの発行**

- 実装: `App\Http\Controllers\ShareController@store`
- 認証: 必要（Bearer トークン）

`expires_at` を省略すると無期限。`share_url` はフロントエンドの `/share/{token}`。

**パスパラメータ**: `annotation`

**リクエストのバリデーション**（FormRequest から自動取得）

| フィールド | ルール |
|---|---|
| `expires_at` | `nullable` `date` `after:now` |

**リクエスト例**

```json
{
    "expires_at": null
}
```

**レスポンス**

- `201` 発行成功

```json
{
    "data": {
        "token": "64文字のランダム文字列",
        "share_url": "https://spovie.example.vercel.app/share/64文字...",
        "expires_at": null,
        "created_at": "2026-10-10T10:40:00+09:00"
    }
}
```

- `403` 権限なし

---

## GET `/api/share/{token}`

**共有リンクの閲覧（認証不要）**

- 実装: `App\Http\Controllers\ShareController@show`
- 認証: 不要

未ログインで見られる。チーム名など内部情報は含めない。アップロード動画は `file_url`（一時署名URL）で再生する。

**パスパラメータ**: `token`

**レスポンス**

- `200` 共有内容

```json
{
    "data": {
        "annotation": {
            "id": 45,
            "start_seconds": 83,
            "end_seconds": 91,
            "canvas_data": {
                "canvas_width": 1280,
                "canvas_height": 720,
                "objects": [
                    {
                        "type": "Circle",
                        "left": 400,
                        "top": 220,
                        "radius": 40,
                        "stroke": "#ff3b30",
                        "strokeWidth": 4,
                        "fill": ""
                    }
                ]
            },
            "comment": "3番のヘルプが0.5秒遅い"
        },
        "video": {
            "type": "youtube",
            "youtube_video_id": "dQw4w9WgXcQ",
            "file_url": null,
            "title": "準決勝 前半"
        },
        "expires_at": null
    }
}
```

- `404` リンクが存在しない

```json
{
    "message": "共有リンクが見つかりません"
}
```

- `410` 有効期限切れ

```json
{
    "message": "この共有リンクは有効期限が切れています"
}
```


---

## GET `/api/annotations/{annotation}/comments`

**コメント一覧**

- 実装: `App\Http\Controllers\CommentController@index`
- 認証: 必要（Bearer トークン）

古い順。`is_own` は自分のコメントかどうか。

**パスパラメータ**: `annotation`

**レスポンス**

- `200` コメントの配列

```json
{
    "data": [
        {
            "id": 7,
            "body": "ナイスカット",
            "user": {
                "id": 2,
                "name": "ボブ"
            },
            "is_own": false,
            "created_at": "2026-10-10T11:00:00+09:00"
        }
    ]
}
```

- `403` 権限なし

---

## POST `/api/annotations/{annotation}/comments`

**コメントの投稿**

- 実装: `App\Http\Controllers\CommentController@store`
- 認証: 必要（Bearer トークン）

投稿後、同じアノテーションを開いている他の人へ `comment.created` を配信する（投稿者本人には送らない）。

**パスパラメータ**: `annotation`

**リクエストのバリデーション**（FormRequest から自動取得）

| フィールド | ルール |
|---|---|
| `body` | `required` `string` `max:1000` |

**リクエスト例**

```json
{
    "body": "ナイスカット"
}
```

**レスポンス**

- `201` 投稿成功

```json
{
    "data": {
        "id": 7,
        "body": "ナイスカット",
        "user": {
            "id": 2,
            "name": "ボブ"
        },
        "is_own": true,
        "created_at": "2026-10-10T11:00:00+09:00"
    }
}
```

- `403` 権限なし

---

## DELETE `/api/comments/{comment}`

**コメントの削除**

- 実装: `App\Http\Controllers\CommentController@destroy`
- 認証: 必要（Bearer トークン）

投稿者本人のみ。削除後 `comment.deleted` を配信する。

**パスパラメータ**: `comment`

**レスポンス**

- `200` 削除成功

```json
{
    "message": "コメントを削除しました"
}
```

- `403` 投稿者ではない

---

## GET `/api/teams`

**所属チーム一覧**

- 実装: `App\Http\Controllers\TeamController@index`
- 認証: 必要（Bearer トークン）

**レスポンス**

- `200` チームの配列

```json
{
    "data": [
        {
            "id": 3,
            "name": "FC スポビー",
            "invite_token": "Zk3...（64文字のランダム文字列）",
            "invite_url": "https://spovie.example.vercel.app/teams/join/Zk3...",
            "owner": {
                "id": 1,
                "name": "アリス"
            },
            "members": [
                {
                    "id": 1,
                    "name": "アリス",
                    "email": "alice@example.com",
                    "role": "owner"
                },
                {
                    "id": 2,
                    "name": "ボブ",
                    "email": "bob@example.com",
                    "role": "member"
                }
            ],
            "created_at": "2026-10-09T09:00:00+09:00"
        }
    ]
}
```


---

## POST `/api/teams`

**チームの作成**

- 実装: `App\Http\Controllers\TeamController@store`
- 認証: 必要（Bearer トークン）

作成者がオーナー兼最初のメンバーになる。招待トークン（64文字）が自動生成される。

**リクエストのバリデーション**（FormRequest から自動取得）

| フィールド | ルール |
|---|---|
| `name` | `required` `string` `max:100` |

**リクエスト例**

```json
{
    "name": "FC スポビー"
}
```

**レスポンス**

- `201` 作成成功

```json
{
    "data": {
        "id": 3,
        "name": "FC スポビー",
        "invite_token": "Zk3...（64文字のランダム文字列）",
        "invite_url": "https://spovie.example.vercel.app/teams/join/Zk3...",
        "owner": {
            "id": 1,
            "name": "アリス"
        },
        "members": [
            {
                "id": 1,
                "name": "アリス",
                "email": "alice@example.com",
                "role": "owner"
            },
            {
                "id": 2,
                "name": "ボブ",
                "email": "bob@example.com",
                "role": "member"
            }
        ],
        "created_at": "2026-10-09T09:00:00+09:00"
    }
}
```


---

## GET `/api/teams/invite/{token}`

**招待トークンからチームを確認**

- 実装: `App\Http\Controllers\TeamController@invite`
- 認証: 必要（Bearer トークン）

参加前の確認用。トークンを知っている人にだけ見せる想定。

**パスパラメータ**: `token`

**レスポンス**

- `200` チーム

```json
{
    "data": {
        "id": 3,
        "name": "FC スポビー",
        "invite_token": "Zk3...（64文字のランダム文字列）",
        "invite_url": "https://spovie.example.vercel.app/teams/join/Zk3...",
        "owner": {
            "id": 1,
            "name": "アリス"
        },
        "members": [
            {
                "id": 1,
                "name": "アリス",
                "email": "alice@example.com",
                "role": "owner"
            },
            {
                "id": 2,
                "name": "ボブ",
                "email": "bob@example.com",
                "role": "member"
            }
        ],
        "created_at": "2026-10-09T09:00:00+09:00"
    }
}
```

- `404` トークンが無効

---

## POST `/api/teams/join`

**招待トークンでチームに参加**

- 実装: `App\Http\Controllers\TeamController@join`
- 認証: 必要（Bearer トークン）

冪等。既にメンバーなら何も変えずに 200 を返す。

**リクエストのバリデーション**（FormRequest から自動取得）

| フィールド | ルール |
|---|---|
| `invite_token` | `required` `string` |

**リクエスト例**

```json
{
    "invite_token": "Zk3...（64文字）"
}
```

**レスポンス**

- `200` 参加後のチーム

```json
{
    "data": {
        "id": 3,
        "name": "FC スポビー",
        "invite_token": "Zk3...（64文字のランダム文字列）",
        "invite_url": "https://spovie.example.vercel.app/teams/join/Zk3...",
        "owner": {
            "id": 1,
            "name": "アリス"
        },
        "members": [
            {
                "id": 1,
                "name": "アリス",
                "email": "alice@example.com",
                "role": "owner"
            },
            {
                "id": 2,
                "name": "ボブ",
                "email": "bob@example.com",
                "role": "member"
            }
        ],
        "created_at": "2026-10-09T09:00:00+09:00"
    }
}
```

- `404` 招待リンクが無効

```json
{
    "message": "招待リンクが無効です"
}
```


---

## GET `/api/teams/{team}`

**チーム詳細（メンバー一覧付き）**

- 実装: `App\Http\Controllers\TeamController@show`
- 認証: 必要（Bearer トークン）

メンバーのみ。

**パスパラメータ**: `team`

**レスポンス**

- `200` チーム

```json
{
    "data": {
        "id": 3,
        "name": "FC スポビー",
        "invite_token": "Zk3...（64文字のランダム文字列）",
        "invite_url": "https://spovie.example.vercel.app/teams/join/Zk3...",
        "owner": {
            "id": 1,
            "name": "アリス"
        },
        "members": [
            {
                "id": 1,
                "name": "アリス",
                "email": "alice@example.com",
                "role": "owner"
            },
            {
                "id": 2,
                "name": "ボブ",
                "email": "bob@example.com",
                "role": "member"
            }
        ],
        "created_at": "2026-10-09T09:00:00+09:00"
    }
}
```

- `403` メンバーではない

---

## DELETE `/api/teams/{team}`

**チームの削除**

- 実装: `App\Http\Controllers\TeamController@destroy`
- 認証: 必要（Bearer トークン）

オーナーのみ。チームの動画は投稿者の個人動画に戻る。

**パスパラメータ**: `team`

**レスポンス**

- `200` 削除成功

```json
{
    "message": "チームを削除しました"
}
```

- `403` オーナーではない

---

## DELETE `/api/teams/{team}/members/{user}`

**メンバーの削除 / 脱退**

- 実装: `App\Http\Controllers\TeamController@removeMember`
- 認証: 必要（Bearer トークン）

オーナーは任意のメンバーを外せる。メンバーは自分自身のみ（脱退）。オーナーは外せず、脱退もできない（422）。

**パスパラメータ**: `team`、`user`

**レスポンス**

- `200` 成功

```json
{
    "message": "メンバーを削除しました"
}
```

- `403` 他のメンバーを外そうとした（オーナー以外）
- `404` そのチームのメンバーではない
- `422` オーナーは削除・脱退できない

```json
{
    "message": "オーナーは削除・脱退できません"
}
```


---

## GET `/api/teams/{team}/videos`

**チームの動画一覧**

- 実装: `App\Http\Controllers\TeamController@videos`
- 認証: 必要（Bearer トークン）

メンバーのみ。1 ページ 20 件。

**パスパラメータ**: `team`

**クエリパラメータ**

| 名前 | 説明 |
|---|---|
| `page` | ページ番号 |
| `per_page` | 件数 |

**レスポンス**

- `200` 動画の配列とページ情報

```json
{
    "data": [
        {
            "id": 12,
            "type": "youtube",
            "youtube_video_id": "dQw4w9WgXcQ",
            "file_url": null,
            "title": "準決勝 前半",
            "team": {
                "id": 3,
                "name": "FC スポビー"
            },
            "created_at": "2026-10-10T10:00:00+09:00"
        }
    ],
    "links": {
        "first": "https://api.example.com/api/videos?page=1",
        "last": "https://api.example.com/api/videos?page=3",
        "prev": null,
        "next": "https://api.example.com/api/videos?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 3,
        "links": [
            {
                "url": null,
                "label": "&laquo; Previous",
                "active": false
            },
            {
                "url": "https://api.example.com/api/videos?page=1",
                "label": "1",
                "active": true
            }
        ],
        "path": "https://api.example.com/api/videos",
        "per_page": 20,
        "to": 20,
        "total": 45
    }
}
```

- `403` メンバーではない

---

## POST `/api/clips`

**切り抜きジョブの作成**

- 実装: `App\Http\Controllers\ClipController@store`
- 認証: 必要（Bearer トークン）

**アップロード動画のみ**（YouTube 動画は著作権ポリシーにより 422）。FFmpeg（`-c copy`）でキュー処理される。状態は `GET /api/clips/{clip}` をポーリングして確認する。

**リクエストのバリデーション**（FormRequest から自動取得）

| フィールド | ルール |
|---|---|
| `video_id` | `required` `integer` `exists:videos,id` |
| `annotation_id` | `nullable` `integer` `exists:annotations,id` |
| `title` | `required` `string` `max:255` |
| `start_seconds` | `required` `integer` `min:0` |
| `end_seconds` | `required` `integer` `gt:start_seconds` |

**リクエスト例**

```json
{
    "video_id": 13,
    "annotation_id": null,
    "title": "ゴールシーン",
    "start_seconds": 5,
    "end_seconds": 15
}
```

**レスポンス**

- `202` 受付（処理中）

```json
{
    "data": {
        "id": 5,
        "video_id": 13,
        "annotation_id": null,
        "title": "ゴールシーン",
        "start_seconds": 5,
        "end_seconds": 15,
        "status": "processing",
        "download_url": null,
        "created_at": "2026-10-10T12:00:00+09:00"
    }
}
```

- `403` 動画の閲覧権限なし
- `422` YouTube 動画は切り抜き不可

```json
{
    "message": "YouTube動画は切り抜き保存できません。直接アップロードした動画のみ対応しています。"
}
```


---

## GET `/api/clips/{clip}`

**切り抜きの状態**

- 実装: `App\Http\Controllers\ClipController@show`
- 認証: 必要（Bearer トークン）

`status` は `processing` / `done` / `error`。`done` のときだけ `download_url`（推測できないトークン付き）が入る。

**パスパラメータ**: `clip`

**レスポンス**

- `200` クリップ

```json
{
    "data": {
        "id": 5,
        "video_id": 13,
        "annotation_id": null,
        "title": "ゴールシーン",
        "start_seconds": 5,
        "end_seconds": 15,
        "status": "done",
        "download_url": "https://api.example.com/api/clips/5/download/40文字のトークン",
        "created_at": "2026-10-10T12:00:00+09:00"
    }
}
```

- `403` 権限なし

---

## GET `/api/clips/{clip}/download/{token}`

**切り抜き動画のダウンロード（認証不要）**

- 実装: `App\Http\Controllers\ClipController@download`
- 認証: 不要

LINE などで共有できるよう認証は不要だが、URL に `download_token`（40文字）が必要。トークン不一致・未完了・ファイル無しは 404。S3 互換ストレージでは一時署名URLへ 302 リダイレクトする。

**パスパラメータ**: `clip`、`token`

**レスポンス**

- `200` mp4 ファイル（ローカルディスクの場合）
- `302` 署名付きURLへリダイレクト（S3 互換）
- `404` クリップが見つからない

---

## GET|POST `/api/broadcasting/auth`

**WebSocket（Pusher）の private チャンネル認証**

- 実装: `Illuminate\Broadcasting\BroadcastController@authenticate`
- 認証: 必要（Bearer トークン）

Laravel Echo の `authorizer` から呼ばれる。Bearer 認証。`private-annotation.{id}` / `private-video.{id}` は、その動画を閲覧できるユーザー（投稿者・チームメンバー）のみ許可。通知の仕様は「リアルタイムイベント」を参照。

**リクエスト例**

```json
{
    "socket_id": "1234.5678",
    "channel_name": "private-annotation.45"
}
```

**レスポンス**

- `200` 許可

```json
{
    "auth": "pusher-key:署名"
}
```

- `403` 購読権限なし
- `401` 未認証

---

## GET `/api/health`

**ヘルスチェック（認証不要）**

- 実装: `App\Http\Controllers\HealthController`
- 認証: 不要

DB に接続できれば 200、できなければ 503。稼働監視と Railway の healthcheck に使う。

**レスポンス**

- `200` 正常

```json
{
    "data": {
        "status": "ok",
        "database": "ok"
    }
}
```

- `503` DB に接続できない

```json
{
    "data": {
        "status": "error",
        "database": "error"
    }
}
```


---

## リアルタイムイベント（Pusher Channels）

REST ではなく WebSocket で配信される通知。クライアントは Laravel Echo で private チャンネルを購読する（認証は `/api/broadcasting/auth`）。イベント名は `broadcastAs()` の値で、Echo では先頭に `.` を付けて購読する（例: `.comment.created`）。イベントはキュー経由で worker が送信し、操作した本人（`X-Socket-ID`）には送らない。

| チャンネル | イベント | ペイロード | 発火タイミング |
|---|---|---|---|
| `private-annotation.{annotationId}` | `comment.created` | `{ "comment": { id, body, user: {id, name}, is_own, created_at } }` | コメント投稿 |
| `private-annotation.{annotationId}` | `comment.deleted` | `{ "id": 7, "annotation_id": 45 }` | コメント削除 |
| `private-video.{videoId}` | `annotation.created` | `{ "annotation": { id, video_id, start_seconds, end_seconds, comment, created_at } }` | アノテーション保存 |

- `comment.created` の `is_own` は閲覧者ごとに異なるため、クライアントは `user.id` で判定し直す。
- `annotation.created` に `canvas_data` は含めない（Pusher の約 10KB 上限対策）。クライアントは一覧を再取得する。
- 切断中の取りこぼしは、再接続時に一覧を取得し直して補う。
- チャンネルの購読権限: その動画を閲覧できるユーザー（投稿者、チーム動画ならチームメンバー）。
