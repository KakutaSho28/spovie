# Spovie — CLAUDE.md

Spovie はスポーツ動画アノテーション Web アプリ（YouTube / アップロード mp4 の上にペン・矢印・テキストを描き、ループ区間・コメント付きで共有する）。
v1 の作業計画は `claude-code-prompt.md`（WP0〜WP7）を参照。

## Stack

| Layer | Tech |
|---|---|
| Frontend | React 18 / TypeScript (strict) / Vite 5 / Fabric.js v6 / Zustand / Axios / YouTube IFrame API |
| Backend | Laravel 10 / PHP 8.2 / Sanctum (Bearer token) / Queue (`database` driver) / FFmpeg |
| DB | MySQL 8.0（テストは sqlite in-memory） |
| Local infra | Docker Compose（nginx / php-fpm / queue worker / mysql / node） |

## Directory map

```
backend/            Laravel 本体（コミット済み。overlay 方式は廃止）
  app/Http/Controllers/  Auth, Video, VideoUpload, Annotation, Share, Clip, Team, Comment
  app/Http/Requests/     FormRequest（バリデーション）
  app/Http/Resources/    API Resource（レスポンス整形）
  app/Services/          業務ロジック（Controller から呼ぶ）
  app/Policies/          認可
  app/Jobs/ProcessClipJob.php  FFmpeg 切り抜き（-c copy）
  app/Models/            User, Video, Annotation, ShareLink, Clip, Team, TeamMember, Comment
  database/factories/    テスト用 Factory
  routes/api.php         全 API ルート（/api プレフィックス）
  tests/Feature/         機能テスト
frontend/src/
  api/client.ts          Axios（トークン自動付与）
  store/auth.ts          Zustand 認証ストア
  components/            AnnotationCanvas（Fabric.js）, ClipModal, CommentThread, Layout …
  hooks/                 useYouTubePlayer, useHtml5VideoLoop
  pages/                 画面コンポーネント
  types/                 型定義
docker/             nginx / php / mysql 設定
scripts/setup-backend.sh  初回セットアップ（composer install, .env, key, migrate, storage:link）
frontend/e2e/        Playwright E2E（API モック、バックエンド不要）
.github/workflows/ci.yml  PR ごとに backend test + frontend build + e2e
```

## How to run

```bash
cp .env.example .env
docker compose up -d --build
bash scripts/setup-backend.sh        # 初回のみ
# App: http://localhost  /  API: http://localhost/api  /  Vite: http://localhost:5173
```

## Tests / build

```bash
cd backend && php artisan test         # sqlite :memory:（phpunit.xml で設定）
cd backend && php artisan route:list   # ルート競合がないこと
cd frontend && npm run build           # tsc（strict）+ vite build
cd frontend && npm run e2e             # Playwright（API はモック。初回は npx playwright install chromium）
```

ホストで frontend を動かすときは `npm ci` をホストで実行する（コンテナで入れた node_modules は Linux 用バイナリのため）。

## Coding rules

- Frontend: TypeScript strict、`any` 禁止。UI 文言は日本語。
- Backend: バリデーションは FormRequest、レスポンスは Resource、認可は Policy（app/Policies）+ `$this->authorize()`。拒否時は Handler が共通の 403 `{"message":"この操作は許可されていません"}` を返す。
- レスポンス形式: 成功 `{ "data": ... }`、エラー / メッセージ `{ "message": ... }`。
- アノテーション座標は画面サイズに依存しない形で保存し、任意サイズで再描画できること。
- 新しい環境変数は必ず `.env.example`（root / backend / frontend の該当箇所）に追加する。秘密情報はコミットしない。
- スコープ外のものは作らない（`claude-code-prompt.md` §5）。

## Backend layering（service-first）

| Layer | 役割 | やらないこと |
|---|---|---|
| FormRequest | バリデーションのみ | 認可・業務ロジック |
| Policy (`app/Policies`) | 認可のみ | クエリ・更新処理 |
| Controller | 受信 → `$this->authorize()` → Service 呼び出し → Resource を返す。1アクション ~15行まで | 業務ロジック、複数ステップの DB 処理、ファイル処理、トークン生成、ジョブ dispatch |
| Service (`app/Services`) | 業務ロジックとトランザクション。依存はコンストラクタ注入。業務ルール違反は `ServiceException`（Handler が `{message}` + status に変換） | `Request` オブジェクトを受け取る、JSON を組み立てる |
| Resource | JSON 整形の唯一の場所 | — |

- 再利用するクエリ条件は Eloquent の local scope（例: `Video::visibleTo($user)`）にする。**Repository パターンは導入しない**（`app/Repositories` を作らない）。
- **新機能（WP4 以降）は service-first で書く**: まず Service とそのテスト（`tests/Feature/Services`）、次に Controller / Route / Resource。
- 新しい Service には Feature テストを必ず付ける。
- `env()` は `config/` の中でのみ使う（本番の `config:cache` 後は null になる）。アプリコードは `config()` を使う。

## Storage（WP2）

- ファイル操作は `MediaStorageService`（既定ディスク `FILESYSTEM_DISK` を使う）経由。`Storage::disk('public')` などを直接書かない。
- ローカル開発は `public`、本番は `s3`（AWS S3 / Cloudflare R2。`AWS_ENDPOINT` / `AWS_USE_PATH_STYLE_ENDPOINT` / `AWS_URL`）。
- 非公開バケットの動画再生・クリップ DL は一時署名 URL（既定 60 分、`MEDIA_URL_TTL_MINUTES`）。公開バケットなら `MEDIA_TEMPORARY_URLS=false`。
- クリップ生成は `ClipProcessingService`（S3 は一時ファイル経由で FFmpeg、失敗時も一時ファイル削除）。FFmpeg 呼び出しは `App\Support\Ffmpeg`（テストで差し替える）。
- アップロード上限は `UPLOAD_MAX_MB`（既定 200MB。プロキシのタイムアウト対策）。UI 側は `VITE_MAX_UPLOAD_MB`、nginx/php.ini も合わせる。

## Deploy（WP3）

- `main` への push で Railway（API: `backend/Dockerfile`）と Vercel（SPA: `frontend/`）が自動デプロイされる。手順・環境変数一覧は `docs/deployment.md`。
- 本番コンテナは起動時に `migrate --force` → `config:cache` → `route:cache`。**ルートにクロージャを使わない**（route:cache が失敗する。`DeploymentConfigTest` が検知）。
- `env()` は config ファイル内だけで使う。CORS は `CORS_ALLOWED_ORIGINS`（ワイルドカード不可）。
- 稼働確認は `GET /api/health`（DB 接続）と `bash scripts/smoke-test.sh <api-url> [frontend-url]`。
- ダッシュボード操作（Railway / Vercel / R2 / Pusher）は人間が行う。必ず「Manual steps」として手順を出す。

## Realtime（WP5, Pusher Channels）

- イベント（`app/Events`）: `CommentCreated` / `CommentDeleted` → `private-annotation.{id}`、`AnnotationCreated` → `private-video.{id}`。すべて `ShouldBroadcast`（キュー経由で worker が送信）。発火は **Service 内**で `broadcast(new X)->toOthers()`。
- チャンネル認可は `routes/channels.php`（動画の閲覧権限 = 投稿者 / チームメンバー）。認証エンドポイントは `/api/broadcasting/auth`（`auth:sanctum`）。
- 通知ペイロードは小さく保つ（Pusher は約10KB上限）。`canvas_data` は含めず、クライアントが再取得する。`is_own` は閲覧者依存なので通知に含めず、クライアントが `user.id` で判定する。
- フロント: `src/lib/echo.ts`（シングルトン。`VITE_PUSHER_APP_KEY` 未設定なら null でリアルタイム無効）、`src/hooks/useRealtime.ts`（`useChannelEvent` / `useOnReconnect` / `useRealtimeStatus`）。API クライアントは `X-Socket-ID` を付ける。コメントは id で重複排除（`src/lib/commentList.ts`）。再接続したら一覧を取得し直す。
- テスト: バックエンドは `Event::fake` とダミーキー（Pusher への通信なし）。フロントの E2E は `e2e/helpers/fakePusher.ts` で WebSocket を偽の Pusher サーバーに差し替える（キー不要）。リアルタイムの E2E は Pusher キー設定済みの dev サーバー（ポート 5175）を使う。
- ローカルで実際の Pusher を試すには `backend/.env`（`BROADCAST_DRIVER=pusher` + `PUSHER_*`）、root `.env`（`VITE_PUSHER_*`）を設定し、queue worker を起動しておく。

## PWA（WP6）

- `vite-plugin-pwa`（`frontend/vite.config.ts`）。アプリシェルはプリキャッシュ、`GET /api/*` は NetworkFirst（最大5分）。**動画ファイル・クリップ DL・`/api/broadcasting/auth` は絶対にキャッシュしない**（NetworkOnly を先に並べる）。
- API キャッシュ（`spovie-api`）はユーザー固有データなので、ログイン/ログアウト時に破棄する（`src/lib/pwa.ts`、`store/auth.ts`）。
- オフライン UI: `OfflineBanner`、`OfflineFallback`、`useOnlineStatus`。接続が必要な操作（保存・投稿・YouTube 再生）はオフライン中に無効化し、理由を明示する。
- E2E は Chromium の `setOffline()` が Service Worker に効かない点に注意（モック API サーバー側で接続を切る）。詳細・Lighthouse 結果は `docs/pwa.md`。
- アイコンは `frontend/public/icons`（再生成: `node scripts/generate-icons.mjs`）。

## Copyright policy（変更禁止）

- **YouTube 動画**: アノテーション + 共有リンクのみ。切り抜き・動画データの保存はしない（座標データのみ保存）。
- **アップロード動画**のみ切り抜き（FFmpeg）とダウンロードが可能。

## Git workflow

- WP ごとにブランチ（`feature/wp1-fixes` など）→ `develop` へ PR → 検証後 `develop` → `main`。
- コミットは小さく、Conventional Commits。**コミットメッセージは日本語**で書く（既存の push 済みコミットは書き換えない）。
  - 形式: `<type>(<scope>): <日本語の要約>`
  - type: `feat` / `fix` / `refactor` / `test` / `docs` / `chore` / `ci`
  - scope: `backend` / `frontend` / `docker` / `ci` など（任意）
  - 本文（body）も日本語。「何を」より「なぜ」を書く
  - 例: `fix(frontend): 小さい画面でアノテーションの図形が拡大される問題を修正`
  - 末尾の Co-Authored-By などのトレーラー行はそのまま残す
- **PR のタイトルと説明も日本語**で書く。
- `main` への push が本番デプロイのトリガー（WP3 以降）。
- 作業完了の前に必ず test / route:list / build を実行し、実行したものを報告する。
