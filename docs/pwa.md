# PWA 対応と Lighthouse 結果

## 実装内容

| 項目 | 内容 |
|---|---|
| プラグイン | `vite-plugin-pwa`（Workbox）、`registerType: 'autoUpdate'`（新しい版は自動で適用） |
| マニフェスト | `name` / `short_name`: Spovie、`display: standalone`、`theme_color: #ff8a3d`、`background_color: #101513`、`lang: ja` |
| アイコン | 192 / 512 / maskable 512（`frontend/public/icons`）、`apple-touch-icon`（180）、`favicon.svg`。いずれもこのプロジェクトのために自作した図形（第三者のロゴは不使用）。再生成: `cd frontend && node scripts/generate-icons.mjs` |
| アプリシェル | HTML / JS / CSS / アイコンをプリキャッシュ。SPA なので、どの画面の遷移もシェルで開く（`navigateFallback`） |
| オフライン表示 | 画面上部に「オフラインです」バナー。キャッシュに無い画面は空白でなく「オフラインのため〇〇を表示できません」の案内。YouTube の再生・保存・投稿・コメントに接続が必要なことを UI に明記し、保存ボタンはオフライン中は無効 |
| インストール | `beforeinstallprompt` を捕捉し、インストール可能なときだけ「ホーム画面に追加」ボタンを表示 |

## キャッシュ戦略

| 対象 | 戦略 | 備考 |
|---|---|---|
| `POST /api/broadcasting/auth` | キャッシュしない（NetworkOnly） | WebSocket 認証。POST なので元々対象外だが、明示的に除外 |
| `/api/clips/*/download/*` | キャッシュしない（NetworkOnly） | 動画データ |
| 動画ファイル（`.mp4` 等、`video` / `audio`、Range リクエスト） | キャッシュしない（NetworkOnly） | 容量が大きく、著作権ポリシー上も端末に保存しない |
| `GET /api/*` | NetworkFirst（3 秒で諦めてキャッシュ）、最大 5 分、最大 50 件、HTTP 200 のみ | キャッシュ名 `spovie-api` |
| アプリシェル | プリキャッシュ | ビルドごとに自動更新 |

- API のキャッシュは URL 単位で、ユーザーごとのデータが入る。**ログイン時・ログアウト時に `spovie-api` を破棄**し、共用端末で別ユーザーのデータがオフライン時に見えないようにしている。
- 切断中のコメントなどの取りこぼしは、再接続時の一覧再取得で補う（WP5）。

## テスト

`frontend/e2e/pwa.spec.ts`（本番ビルドを `vite preview` で配信、実 HTTP のモック API につなぐ）:

1. マニフェストの内容、アイコンが取得できること、`theme-color` / `apple-touch-icon`
2. Service Worker が登録・有効化され、ページを制御すること
3. オフラインでアプリが起動し、表示済みの一覧がキャッシュから出ること、バナー、キャッシュに無い画面の案内、復帰でバナーが消えること
4. 動画・クリップ・WebSocket 認証がキャッシュされないこと
5. ログアウトで API キャッシュが消えること
6. インストールボタンの表示とプロンプト呼び出し

注意: Chromium の `context.setOffline()` は Service Worker 自身の通信には効かない。そのためオフラインのテストでは、モック API サーバー側で接続を切って（`/__down`）ネットワーク断を再現している。この方式でないと、キャッシュを使っていなくてもテストが通ってしまう（実際に一度そうなっており、修正した）。

## Lighthouse

### ローカルの本番ビルド（2026-10-11 実施）

- 対象: `vite build` した成果物を `vite preview` で配信した `http://localhost:5190/login`
- ツール: Lighthouse **11.7.1**（PWA カテゴリがある最後の系列。Lighthouse 12 以降は PWA カテゴリが廃止され、インストール可否は Chrome DevTools の Application タブで確認する）
- 結果: **PWA スコア 1.0（全自動監査をクリア）**

| 監査 | 結果 |
|---|---|
| installable-manifest（インストール可能なマニフェスト） | 合格 |
| splash-screen（カスタムスプラッシュ） | 合格 |
| themed-omnibox（テーマカラー） | 合格 |
| maskable-icon | 合格 |
| viewport / content-width | 合格 |
| 手動確認項目（cross-browser / page-transitions / each-page-has-url） | 自動採点なし |

### 本番 URL（未実施）

本番 URL がまだ無いため、本番に対する実行は**未実施**。デプロイ後に次を実行し、結果をここに追記する。

```bash
npx lighthouse@11.7.1 https://<vercel-domain>/login --only-categories=pwa \
  --output=json --output-path=./lighthouse-prod.json --chrome-flags="--headless=new"
```

あわせて Chrome DevTools → Application → Manifest で「Installability」にエラーが無いこと、実機（Android Chrome）で「ホーム画面に追加」が出ることを確認する。
