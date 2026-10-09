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
.github/workflows/ci.yml  PR ごとに backend test + frontend build
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
```

ホストで frontend を動かすときは `npm ci` をホストで実行する（コンテナで入れた node_modules は Linux 用バイナリのため）。

## Coding rules

- Frontend: TypeScript strict、`any` 禁止。UI 文言は日本語。
- Backend: バリデーションは FormRequest、レスポンスは Resource、認可は Policy / コントローラで行う。
- レスポンス形式: 成功 `{ "data": ... }`、エラー / メッセージ `{ "message": ... }`。
- アノテーション座標は画面サイズに依存しない形で保存し、任意サイズで再描画できること。
- 新しい環境変数は必ず `.env.example`（root / backend / frontend の該当箇所）に追加する。秘密情報はコミットしない。
- スコープ外のものは作らない（`claude-code-prompt.md` §5）。

## Copyright policy（変更禁止）

- **YouTube 動画**: アノテーション + 共有リンクのみ。切り抜き・動画データの保存はしない（座標データのみ保存）。
- **アップロード動画**のみ切り抜き（FFmpeg）とダウンロードが可能。

## Git workflow

- WP ごとにブランチ（`feature/wp1-fixes` など）→ `develop` へ PR → 検証後 `develop` → `main`。
- コミットは小さく、Conventional Commits（`feat:` `fix:` `docs:` `chore:` `test:`）。
- `main` への push が本番デプロイのトリガー（WP3 以降）。
- 作業完了の前に必ず test / route:list / build を実行し、実行したものを報告する。
