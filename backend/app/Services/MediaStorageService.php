<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 動画・クリップの保存先（local public / S3 互換）の違いを吸収する。
 */
class MediaStorageService
{
    public function diskName(): string
    {
        return config('filesystems.default');
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    /** ローカルドライバ（ファイルパスで直接アクセスできる）か */
    public function isLocal(): bool
    {
        return config("filesystems.disks.{$this->diskName()}.driver") === 'local';
    }

    /** 一時署名 URL で配信するか */
    public function usesTemporaryUrls(): bool
    {
        $configured = config('media.temporary_urls');

        if ($configured !== null && $configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        return config("filesystems.disks.{$this->diskName()}.driver") === 's3';
    }

    /** ブラウザ再生用の URL */
    public function playbackUrl(string $path): string
    {
        if ($this->usesTemporaryUrls()) {
            return $this->disk()->temporaryUrl($path, now()->addMinutes(config('media.url_ttl_minutes')));
        }

        return $this->disk()->url($path);
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    /**
     * ダウンロード応答。署名 URL を使う構成ではアプリを経由せずストレージへリダイレクトする。
     */
    public function download(string $path, string $fileName): StreamedResponse|RedirectResponse
    {
        if ($this->usesTemporaryUrls()) {
            return redirect()->away($this->disk()->temporaryUrl(
                $path,
                now()->addMinutes(config('media.url_ttl_minutes')),
                ['ResponseContentDisposition' => 'attachment; filename="' . addslashes($fileName) . '"'],
            ));
        }

        return $this->disk()->download($path, $fileName);
    }
}
