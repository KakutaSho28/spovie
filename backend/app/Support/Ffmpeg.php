<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * FFmpeg 呼び出しの薄いラッパー（テストでは差し替える）
 */
class Ffmpeg
{
    /**
     * 再エンコードなし（-c copy）で指定区間を切り出す。
     *
     * @throws RuntimeException 失敗時（stderr をメッセージに含む）
     */
    public function trim(string $input, string $output, int $startSeconds, int $endSeconds, int $timeout = 600): void
    {
        $process = new Process([
            'ffmpeg',
            '-i', $input,
            '-ss', (string) $startSeconds,
            '-to', (string) $endSeconds,
            '-c', 'copy',
            '-y',
            $output,
        ]);
        $process->setTimeout($timeout);
        $process->run();

        if (! $process->isSuccessful() || ! file_exists($output)) {
            throw new RuntimeException($process->getErrorOutput() ?: 'ffmpeg failed');
        }
    }
}
