#!/bin/bash
# =========================================================
# Spovie バックエンド初期セットアップスクリプト
# 依存関係のインストール・.env 作成・マイグレーションを行う
# 実行: プロジェクトルートで bash scripts/setup-backend.sh
# =========================================================
set -e

echo "▶ 1/5 依存関係をインストール中..."
docker compose run --rm php composer install --no-interaction

echo "▶ 2/5 .env を設定中..."
if [ ! -f backend/.env ]; then
  cp backend/.env.example backend/.env
fi
sed -i.bak \
  -e 's/^DB_HOST=.*/DB_HOST=mysql/' \
  -e 's/^DB_DATABASE=.*/DB_DATABASE=spovie_db/' \
  -e 's/^DB_USERNAME=.*/DB_USERNAME=spovie_user/' \
  -e 's/^DB_PASSWORD=.*/DB_PASSWORD=spovie_pass/' \
  -e 's/^FILESYSTEM_DISK=.*/FILESYSTEM_DISK=public/' \
  -e 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=database/' \
  backend/.env && rm backend/.env.bak

grep -q '^APP_FRONTEND_URL=' backend/.env || printf '\nAPP_FRONTEND_URL=http://localhost:5173\n' >> backend/.env

echo "▶ 3/5 アプリケーションキーを生成中..."
if ! grep -q '^APP_KEY=base64:' backend/.env; then
  docker compose run --rm php php artisan key:generate
fi

echo "▶ 4/5 マイグレーション実行中..."
docker compose run --rm php php artisan migrate --force

echo "▶ 5/5 ストレージの公開リンクを作成中..."
docker compose run --rm php php artisan storage:link

echo "✅ バックエンドのセットアップが完了しました"
echo "   API: http://localhost/api"
echo "   キューワーカーを再起動してください: docker compose restart queue"
