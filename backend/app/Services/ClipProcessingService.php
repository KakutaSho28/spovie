<?php

namespace App\Services;

use App\Models\Clip;
use App\Support\Ffmpeg;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * クリップの FFmpeg 処理。S3 互換ストレージでは
 * 元動画を一時ファイルへ取得 → ローカルで FFmpeg → 結果をアップロード → 一時ファイル削除（失敗時も）。
 */
class ClipProcessingService
{
    public function __construct(
        private readonly MediaStorageService $media,
        private readonly Ffmpeg $ffmpeg,
    ) {}

    public function process(Clip $clip, int $timeout = 600): void
    {
        $temps = [];

        try {
            $outputRelative = 'clips/' . Str::uuid() . '.mp4';

            if ($this->media->isLocal()) {
                $input = $this->media->disk()->path($clip->video->file_path);
                $this->media->disk()->makeDirectory('clips');
                $output = $this->media->disk()->path($outputRelative);
            } else {
                $input = $temps[] = $this->tempPath();
                $output = $temps[] = $this->tempPath();
                $this->download($clip->video->file_path, $input);
            }

            $this->ffmpeg->trim($input, $output, $clip->start_seconds, $clip->end_seconds, $timeout);

            if (! $this->media->isLocal()) {
                $this->upload($output, $outputRelative);
            }

            $clip->update(['file_path' => $outputRelative, 'status' => Clip::STATUS_DONE]);
        } catch (Throwable $e) {
            Log::error('Clip processing failed', ['clip_id' => $clip->id, 'error' => $e->getMessage()]);
            $clip->update(['status' => Clip::STATUS_ERROR]);
        } finally {
            foreach ($temps as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }

    private function tempPath(): string
    {
        return sys_get_temp_dir() . '/spovie-' . Str::uuid() . '.mp4';
    }

    private function download(string $remotePath, string $localPath): void
    {
        $source = $this->media->disk()->readStream($remotePath);
        if (! is_resource($source)) {
            throw new RuntimeException("source video not found: {$remotePath}");
        }

        $target = fopen($localPath, 'wb');
        stream_copy_to_stream($source, $target);
        fclose($source);
        fclose($target);
    }

    private function upload(string $localPath, string $remotePath): void
    {
        $stream = fopen($localPath, 'rb');
        try {
            $this->media->disk()->writeStream($remotePath, $stream);
        } finally {
            fclose($stream);
        }
    }
}
