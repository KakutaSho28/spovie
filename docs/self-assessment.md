# 自己評価（評価基準 × 根拠）

事実だけを書く。**「実装・テスト済み」と「実環境で確認済み」を区別**し、確認できていないものは未確認と明記する。記載内容は 2026-10-11 時点。

| 記号 | 意味 |
|---|---|
| ✅ | 実装済みで、テストまたは実行で確認した（根拠あり） |
| ⚠️ | 実装・テストは済んでいるが、実環境（本番・実サービス・実機）では未確認 |
| ⬜ | 未実施（ダッシュボード操作など、人の作業が必要） |

## 1. 結論

- **機能と設計書は評価基準 5 相当まで実装済み**。ただし、5 の条件である **(a) 公開サイトの URL が未達**（デプロイは人のダッシュボード操作が必要で、PR #6 の `main` への反映も未完了）。
- そのため **現時点で根拠をもって主張できるのは評価基準 4 まで**（PWA・リアルタイムを含む全機能を実装し、自動テストで確認済み。ただし Pusher・R2/S3・本番は実環境未確認）。公開 URL を作り、下の「5. 5 に到達するために残っていること」を済ませれば 5 を主張できる。

## 2. 評価基準ごとの根拠

| 評価 | 条件 | 状況 | 根拠 |
|---|---|---|---|
| 5 | 全 v1 機能 + 全成果物の提出 | ⬜ 公開 URL のみ未達 | 機能: 下表 / 成果物: 3 章 |
| 4 | PWA・リアルタイムが一部以上、他の主要機能が完成 | ✅ 実装・自動テスト済み / ⚠️ 実環境未確認 | PWA: PR #10、`docs/pwa.md`、`frontend/e2e/pwa.spec.ts` / リアルタイム: PR #9、`backend/tests/Feature/{RealtimeEvents,BroadcastAuth}Test.php`、`frontend/e2e/realtime.spec.ts` |
| 3 | 基本機能（一覧・詳細・投稿）が完成し、公開されている | ✅ 機能 / ⬜ 公開 | 一覧: S03・S05、詳細: S06・S07、投稿: アノテーション・コメント。公開は未実施 |
| 2 | フロント⇔バックエンドの接続確認、設計書が完備 | ✅ API の接続確認 / ⚠️ ブラウザ⇔実バックエンドは自動テスト対象外 / ✅ 設計書 | `scripts/smoke-test.sh`（下記）、`docs/`（3 章） |
| 1 | DB 設計と環境構築のみ | ✅ | `docs/02-basic-design.md` の ER 図、`docs/04-setup.md`、`docker-compose.yml` |

### v1 機能の状況

| 機能（要件 ID） | 実装 | テストによる確認 | 実環境での確認 |
|---|---|---|---|
| F01 認証 | ✅ | PHPUnit（`AuthFlowTest`、`AuthServiceTest`、契約テスト） | ⬜ |
| F02 YouTube 動画の登録・一覧・詳細・削除 | ✅ | PHPUnit（`VideoShowTest`、`VideoListFilterTest`、`AuthorizationTest`） | ⬜ |
| F03 動画アップロード（≤200MB） | ✅ | PHPUnit（`VideoServiceTest`、上限の検証）、本番イメージで 215MB が 413 | ⚠️ R2/S3 への実アップロードは未確認 |
| F04〜F06 アノテーション（描画・ループ・保存・相対位置の再現） | ✅ | Playwright: 1280px→640px の再現（ピクセル検証）、デモデータの復元 | ⬜ |
| F07 共有リンク（YouTube / アップロード） | ✅ | PHPUnit（`ShareTest`、`ShareLinkServiceTest`）、`smoke-test.sh` | ⬜ |
| F08 切り抜き | ✅ | PHPUnit（`ClipServiceTest`、`ClipProcessingServiceTest`、`ClipDownloadTest`）。FFmpeg は偽実装 | ⚠️ 実際の FFmpeg 実行・S3 経由は未確認（本番イメージに FFmpeg 7.1 が入っていることは確認） |
| F17 チーム | ✅ | PHPUnit（`TeamApiTest` ほか、オーナー/メンバー/非メンバー）、Playwright（招待フロー） | ⬜ |
| F18 コメント + リアルタイム | ✅ | PHPUnit（イベント・チャンネル認可）、Playwright（偽 Pusher サーバーで受信・重複排除・再接続） | ⚠️ 実際の Pusher との接続は未確認 |
| F19 PWA | ✅ | Playwright（本番ビルド: マニフェスト・SW・オフライン起動・キャッシュ除外・キャッシュ破棄）、Lighthouse 11.7.1 で PWA スコア 1.0（ローカルの本番ビルド） | ⚠️ 本番 URL への Lighthouse、実機でのインストールは未確認 |
| F20 ページ送り | ✅ | PHPUnit（ページ分割・重複欠落なし）、Playwright | ⬜ |
| F21 公開デモ（シード） | ✅ | PHPUnit（`DemoSeederTest`: 冪等・YouTube のみ・ログイン可）、Playwright（描画の復元） | ⬜ `SEED_DEMO=true` での本番投入は未実施。デモ動画の YouTube ID が現在も再生できるかは未確認 |

## 3. 成果物

| 成果物 | 状況 | 場所 |
|---|---|---|
| (a) 公開サイトの URL | ⬜ 未達 | README の「公開サイト」欄に、デプロイ後に記入する |
| (b) GitHub リポジトリ | ✅ | https://github.com/KakutaSho28/spovie |
| (c) 設計書（DB 設計） | ✅ | `docs/02-basic-design.md`（ER 図: Mermaid `erDiagram`） |
| (c) 設計書（API 定義） | ✅ | `docs/api-endpoints.md`（全 29 ルート。`route:list --json` と FormRequest から自動生成。CI がコードとのずれと、例と実レスポンスの構造のずれを検知） |
| その他の設計書 | ✅ | `docs/01-requirements.md`（要件定義）、`docs/03-detailed-design.md`（画面仕様・画面遷移図）、`docs/04-setup.md`（環境構築）、`docs/deployment.md`、`docs/pwa.md` |
| README | ✅ | `README.md`（概要・機能・構成図・技術スタック・セットアップ・環境変数表・デモアカウント） |

## 4. 求められるスキルの根拠

| スキル | 根拠 |
|---|---|
| React + TypeScript | `frontend/`。`tsconfig.json` は `strict: true`、`src` に `any` は 0 件（`npm run build` が `tsc` を通す）。Zustand、Fabric.js、React Router、カスタムフック |
| PWA | `vite-plugin-pwa`（Workbox）、マニフェスト・アイコン、キャッシュ戦略、オフライン UI、インストールボタン。`docs/pwa.md` |
| Laravel 10 REST API | `backend/`。Sanctum、FormRequest / Policy / Controller / Service / Resource の分離、キュー、Eloquent スコープ、PHPUnit 113 件 |
| Pusher リアルタイム | `ShouldBroadcast` イベント、private チャンネルの認可（`/api/broadcasting/auth`）、Laravel Echo、再接続時の再取得。実キーでの接続は ⚠️ 未確認 |
| GitHub ワークフロー | ブランチ運用（`main` ← `develop` ← `feature/*`）、機能ごとの PR（#1〜#11 + 本 PR）、PR ごとの CI（バックエンドテスト・フロントのビルド・E2E。E2E は #2 から）、日本語の Conventional Commits |
| Vercel / Railway デプロイ | 設定は ✅（`backend/Dockerfile`、`railway.json`、`railway.worker.json`、`frontend/vercel.json`、`docs/deployment.md`）。本番イメージはローカルの Docker + MySQL で起動して `smoke-test.sh` 全成功を確認。**実際のデプロイは ⬜ 未実施** |

## 5. 5 に到達するために残っていること

すべて人の作業（ダッシュボード操作・実環境での確認）。手順は `docs/deployment.md`。

1. ⬜ **PR #6（develop → main）をマージ**する。`main` に `backend/Dockerfile` などが無いと Railway / Vercel を接続できない（本資料の作成時点で #6 は OPEN）。この PR のマージ後に、`develop` の最新（WP4〜WP7）を含む新しい develop → main の PR が必要になる場合がある。
2. ⬜ R2（または S3）、Railway（web・MySQL・worker）、Vercel、Pusher を設定し、環境変数を入れる。
3. ⬜ `bash scripts/smoke-test.sh https://<railway>/api https://<vercel>` が全て成功すること。
4. ⬜ 公開デモを見せる場合は `SEED_DEMO=true` を設定し、デモアカウントでログインできること。
5. ⬜ ブラウザで次を確認する: mp4 のアップロード → 切り抜き → ダウンロード（R2/S3 と FFmpeg の実確認）／2 つのブラウザでのコメントのリアルタイム反映／共有リンクをシークレットウィンドウで開く。
6. ⬜ 本番 URL に Lighthouse（PWA）を実行し、結果を `docs/pwa.md` に追記する。実機（Android Chrome）で「ホーム画面に追加」を確認する。
7. ⬜ README の「公開サイト」欄に URL を記入する。

## 6. 既知の制約・リスク

| 項目 | 内容 |
|---|---|
| Laravel 10 の脆弱性 | `composer audit` に laravel/framework の 4 件が残る。修正版は 12.x / 13.x のみで、10 系では解消できない。`APP_DEBUG=false` で運用し、アプリはメール送信・Laravel 署名付き URL を使っていない。恒久対応は Laravel 12 以降への更新 |
| E2E の API はモック | Playwright は API をモックしている（PWA は実 HTTP のモックサーバー）。実フロントと実バックエンドの結合は、API を叩く `smoke-test.sh` と、デプロイ後の手動確認で補う |
| テストは sqlite | CI とローカルのテストは sqlite。本番は MySQL（外部キーの `ON DELETE SET NULL` などは MySQL でも動く前提だが、チーム削除時の動画の個人化は Service で明示的に行い、DB に依存しないようにしている） |
| 本番イメージの大きさ | 約 1GB（FFmpeg 同梱）。初回ビルドに数分かかる |
| アップロード上限 | 200MB（プロキシのタイムアウト対策）。大きい動画は将来の presigned 直接アップロードが必要 |
| ページ送り | 動画一覧のみ。アノテーション一覧・チーム動画一覧の UI は未対応（API は対応） |
| 通知の取りこぼし | Pusher は取りこぼしを保証しないため、再接続時に一覧を再取得して補う。切断前後の一瞬のずれは起こりうる |
| デモ動画 | シードの YouTube ID（Blender 公式の CC BY 短編映画）が現在も埋め込み再生できるかは未確認。`DEMO_YOUTUBE_ID_1/2` で差し替えられる |

## 7. 検証の記録

| 項目 | 結果 |
|---|---|
| `php artisan test` | 113 件成功（823 アサーション） |
| `npm run e2e`（Playwright） | 28 件成功 |
| `npm run build`（`tsc` strict + `vite build`） | 成功 |
| GitHub Actions CI | 全 PR（#1〜#11）で CI 成功を確認してからマージ（#1 はバックエンドテストとフロントのビルドの 2 ジョブ、#2 以降は E2E を加えた 3 ジョブ） |
| 本番イメージ | ローカルの Docker + MySQL 8 で起動。migrate / キャッシュ生成 / `/api/health` 200 / `smoke-test.sh` 全成功 / FFmpeg 7.1 / 215MB アップロードは 413 |
| Lighthouse 11.7.1（PWA） | ローカルの本番ビルドで 1.0（本番 URL は未実施） |
| Mermaid 図 | README と `docs/` の 7 つの図すべてが描画できることを確認 |

### 作業中に見つけて直した不具合（テストが検出したもの）

| 不具合 | 発見した方法 |
|---|---|
| 共有リンクの作成が毎回 500（`share_links` に `updated_at` が無い） | WP1 のテスト |
| 共有 URL が常に `http://localhost`（`app.frontend_url` が未定義） | WP1 のテスト |
| 小さい画面で開くとアノテーションの図形が約 2 倍に拡大される（拡大縮小の比が逆） | Playwright のピクセル検証 |
| ログイン直後に招待リンクへ戻れない（ガードの競合） | Playwright |
| 再接続しても取りこぼしを補う再取得が走らない（pusher-js は `disconnected` を経由しない） | Playwright（偽 Pusher の切断テスト） |
| オフラインのテストが、キャッシュを使わなくても通っていた（`setOffline` は Service Worker に効かない） | 変異（API をキャッシュしない設定）で失敗しないことに気付いて修正 |
| 動画一覧の並び順が不定で、ページをまたぐと重複・欠落しうる（MySQL） | レビュー（sqlite では再現しない） |
| 動画登録直後のレスポンスにチーム名が無い | 契約テスト（ドキュメントの例と実レスポンスの比較） |
| `scaleX` などが無い図形が描画されない | デモデータの復元テスト |
