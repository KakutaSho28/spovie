# Spovie 🎬

> Sport + Movie — チームの「見る目」を揃える、スポーツ動画アノテーションツール

YouTubeにアップロードされたスポーツ試合映像に、動画の上から直接書き込み（ペン・矢印・テキスト）を加え、ループ区間・コメントと一緒に共有リンクでチームメイトに届けるWebアプリです。

## 技術スタック

| レイヤー | 技術 |
|---|---|
| フロントエンド | React 18 / TypeScript / Vite / Fabric.js / Zustand / YouTube IFrame API |
| バックエンド | Laravel 10 / PHP 8.2 / Sanctum |
| DB | MySQL 8.0 |
| インフラ | Docker / Nginx |

## セットアップ

### 必要なもの
- Docker Desktop
- Git

### 手順

```bash
# 1. クローン
git clone https://github.com/<your-username>/spovie.git
cd spovie

# 2. 環境変数
cp .env.example .env

# 3. コンテナ起動
docker compose up -d --build

# 4. バックエンド初期化（Laravelスケルトン生成 + Spovieコード適用 + migrate）
bash scripts/setup-backend.sh
```

### アクセス

| サービス | URL |
|---|---|
| アプリ（Nginx経由） | http://localhost |
| フロント開発サーバー直 | http://localhost:5173 |
| API | http://localhost/api |

## ディレクトリ構成

```
spovie/
├── docker-compose.yml
├── docker/                 # Nginx / PHP / MySQL の設定
├── backend/                # Laravel 本体（setup-backend.sh が生成）
├── backend-overlay/        # Spovieのアプリケーションコード（backendに上書きされる）
│   ├── app/Http/Controllers/   # Auth / Video / Annotation / Share
│   ├── app/Http/Requests/      # バリデーション
│   ├── app/Http/Resources/     # レスポンス整形
│   ├── app/Models/             # User / Video / Annotation / ShareLink
│   ├── database/migrations/    # videos / annotations / share_links
│   └── routes/api.php
├── frontend/               # React + Vite + TypeScript
│   └── src/
│       ├── api/            # Axiosクライアント（トークン自動付与）
│       ├── store/          # Zustand認証ストア
│       ├── hooks/          # useYouTubePlayer（ループ再生制御）
│       ├── components/     # AnnotationCanvas（Fabric.js）/ Layout
│       ├── pages/          # S01〜S07 の7画面
│       └── types/          # 型定義 + YouTube API型
└── scripts/setup-backend.sh
```

## 実装済み機能（MVP）

### YouTube動画
- YouTube URL登録（watch?v= / youtu.be / embed 対応、サムネイルプレビュー付き）
- 指定区間のループ再生（IFrame API + 250ms監視）
- 共有リンク発行（有効期限対応）と認証不要の閲覧専用共有ページ
- ※ 規約上、切り抜き保存は非対応（座標データのみ保存）

### 直接アップロード動画
- mp4アップロード（ドラッグ&ドロップ / 最大500MB / プログレスバー付き）
- HTML5 videoでの指定区間ループ再生
- FFmpegによる切り抜き保存（Laravel Queueで非同期処理、-c copyで高速トリム）
- 切り抜き動画のダウンロードURL発行（認証不要）+ LINE共有ボタン

### 共通
- ユーザー登録 / ログイン / ログアウト（Sanctumトークン認証）
- Canvasアノテーション：ペン / 矢印 / テキスト / 元に戻す / 選択削除（Fabric.js v6）
- 相対座標（0〜1）での保存・復元 — 異なる画面サイズ間でも描画を正確に再現
- ResizeObserverによるプレーヤーとCanvasのサイズ同期

## ブランチ運用

```
main          # 本番リリース用（直接push禁止）
develop       # 開発統合ブランチ
feature/xxx   # 機能開発
fix/xxx       # バグ修正
```

## ライセンス

Private — All rights reserved.
