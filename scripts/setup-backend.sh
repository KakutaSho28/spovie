#!/bin/bash
# =========================================================
# Spovie バックエンド初期セットアップスクリプト
# Laravel 10 スケルトンを生成し、Spovieのコードを上書きする
# 実行: プロジェクトルートで bash scripts/setup-backend.sh
# =========================================================
set -e

echo "▶ 1/6 Laravel 10 スケルトンを生成中..."
docker compose run --rm php composer create-project laravel/laravel:^10.0 /var/www/backend --no-interaction

echo "▶ 2/6 Spovieのコードを上書き中..."
cp -rf backend-overlay/app backend/
cp -rf backend-overlay/database backend/
cp -rf backend-overlay/routes backend/

echo "▶ 3/6 .env を設定中..."
cp backend/.env.example backend/.env
sed -i.bak \
  -e 's/^DB_HOST=.*/DB_HOST=mysql/' \
  -e 's/^DB_DATABASE=.*/DB_DATABASE=spovie_db/' \
  -e 's/^DB_USERNAME=.*/DB_USERNAME=spovie_user/' \
  -e 's/^DB_PASSWORD=.*/DB_PASSWORD=spovie_pass/' \
  -e 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=database/' \
  backend/.env && rm backend/.env.bak

echo "▶ 4/6 アプリケーションキーを生成中..."
docker compose run --rm php php artisan key:generate

echo "▶ 5/6 マイグレーション実行中..."
docker compose run --rm php php artisan migrate

echo "▶ 6/6 ストレージの公開リンクを作成中..."
docker compose run --rm php php artisan storage:link

echo "✅ バックエンドのセットアップが完了しました"
echo "   API: http://localhost/api"
echo "   キューワーカーを再起動してください: docker compose restart queue"
