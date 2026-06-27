FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip \
    ffmpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN mkdir -p /var/www/backend \
    && composer require league/flysystem-aws-s3-v3 --working-dir=/var/www/backend || true

# 動画アップロード対応（500MBまで）
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/backend
