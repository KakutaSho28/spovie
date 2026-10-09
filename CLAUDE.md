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

## Copyright policy（変更禁止）

- **YouTube 動画**: アノテーション + 共有リンクのみ。切り抜き・動画データの保存はしない（座標データのみ保存）。
- **アップロード動画**のみ切り抜き（FFmpeg）とダウンロードが可能。

## Git workflow

- WP ごとにブランチ（`feature/wp1-fixes` など）→ `develop` へ PR → 検証後 `develop` → `main`。
- コミットは小さく、Conventional Commits（`feat:` `fix:` `docs:` `chore:` `test:`）。
- `main` への push が本番デプロイのトリガー（WP3 以降）。
- 作業完了の前に必ず test / route:list / build を実行し、実行したものを報告する。
