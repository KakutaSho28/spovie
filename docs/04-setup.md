# 環境構築手順

## 1. ローカル開発（Docker）

必要なもの: Docker Desktop、Git。Node 20 以上と PHP 8.2 + Composer はホストでテストを動かす場合のみ必要。

```bash
git clone https://github.com/KakutaSho28/spovie.git && cd spovie
cp .env.example .env                    # DB のパスワード等（ローカル用の値）
docker compose up -d --build            # nginx / php-fpm / queue worker / mysql / vite
bash scripts/setup-backend.sh           # 初回のみ: composer install, backend/.env, APP_KEY, migrate, storage:link
docker compose restart queue            # setup 後にキューワーカーを再起動
```

| URL | 内容 |
|---|---|
| http://localhost | アプリ（nginx が `/api` を Laravel、それ以外を Vite へ振り分け） |
| http://localhost:5173 | Vite 開発サーバー（直接） |
| http://localhost/api/health | ヘルスチェック（`{"data":{"status":"ok","database":"ok"}}`） |

デモデータ: `docker compose exec php php artisan db:seed`（デモアカウントは README 参照。何度実行しても重複しない）。

## 2. テスト

```bash
# バックエンド（PHP 8.2 + Composer がホストにある場合）
cd backend && composer install && php artisan test

# フロントエンド
cd frontend && npm ci
npx playwright install chromium          # 初回のみ
npm run build                            # tsc (strict) + vite build
npm run e2e                              # Playwright（開発サーバーを自動起動。API・Pusher はモック）
```

- ホストで `npm ci` するのは、コンテナ内で入れた `node_modules` が Linux 用のバイナリで、Mac で動かないため。
- バックエンドのテストは sqlite の in-memory DB を使い、MySQL・Docker は不要。
- API 仕様書を更新したとき: `cd backend && php artisan docs:api` を実行して `docs/api-endpoints.md` をコミットする（古いと CI が失敗する）。

## 3. リアルタイム（Pusher）をローカルで試す

1. https://dashboard.pusher.com → Channels → Create app（Cluster は `ap4`（Tokyo）など）。App Keys を控える。
2. `backend/.env`: `BROADCAST_DRIVER=pusher`、`PUSHER_APP_ID` / `PUSHER_APP_KEY` / `PUSHER_APP_SECRET` / `PUSHER_APP_CLUSTER`
3. root の `.env`: `VITE_PUSHER_APP_KEY`（= `PUSHER_APP_KEY`）、`VITE_PUSHER_APP_CLUSTER`
4. `docker compose up -d`（フロントを再起動して環境変数を反映）。**キューワーカーが動いている**ことを確認（通知はワーカーが送る）
5. 2 つのブラウザ（片方はシークレット）で別アカウントでログインし、同じ動画のアノテーション一覧を開く。ヘッダーに「リアルタイム接続中」が出れば成功。片方でコメントすると、もう片方に再読み込みなしで反映される。

キー未設定のままでも、アプリは通常どおり動く（リアルタイム機能だけが無効）。

## 4. 本番（Railway / Vercel / R2 / Pusher）

[deployment.md](deployment.md) を参照。ダッシュボードで行う手順、環境変数の一覧、スモークテスト、リアルタイムの 2 ブラウザ確認手順を載せている。

## 5. トラブルシューティング

| 症状 | 原因と対処 |
|---|---|
| `npm run build` が `@rollup/rollup-darwin-x64` で落ちる | コンテナで入れた `node_modules` が混ざっている。ホストで `rm -rf node_modules && npm ci` |
| 動画をアップロードすると 413 | nginx / php.ini の上限（200MB + 余裕）を超えている。`UPLOAD_MAX_MB` と合わせて確認 |
| 切り抜きが「処理中」のまま | キューワーカーが動いていない。`docker compose restart queue`、ログは `docker compose logs queue` |
| 「リアルタイム接続中」が出ない | `VITE_PUSHER_APP_KEY` 未設定、またはビルド後に変更した（Vite はビルド時に埋め込む）。再ビルド/再起動 |
| コメントが他のブラウザに反映されない | worker が停止している、`BROADCAST_DRIVER` が `pusher` でない、Pusher のキー/クラスタ不一致 |
| オフライン表示を試したい | 本番ビルド（`npm run build && npx vite preview`）で。開発サーバーは Service Worker を登録しない |
