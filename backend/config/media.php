<?php

return [

    /*
    | アップロード動画の最大サイズ（MB）。
    | 本番はプロキシ（Railway 等）のタイムアウトを避けるため 200MB に制限する。
    | 変更する場合は nginx の client_max_body_size / php.ini の upload_max_filesize も合わせる。
    */
    'max_upload_mb' => (int) env('UPLOAD_MAX_MB', 200),

    /*
    | プライベートバケット（S3 / R2）の動画 URL を一時署名 URL で配信するか。
    | 未指定なら、既定ディスクが s3 ドライバのとき true。
    | バケットを公開設定にして AWS_URL で配信する場合は false にする。
    */
    'temporary_urls' => env('MEDIA_TEMPORARY_URLS'),

    /** 一時署名 URL の有効期限（分） */
    'url_ttl_minutes' => (int) env('MEDIA_URL_TTL_MINUTES', 60),

];
