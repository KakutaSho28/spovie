<?php

namespace App\Jobs;

use App\Models\Clip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ProcessClipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** FFmpegの最大実行時間（秒） */
    public int $timeout = 600;

    public function __construct(
        public Clip $clip,
    ) {}

    public function handle(): void
    {
        $clip = $this->clip->fresh();
        if (! $clip) {
            return;
        }

        $diskName = config('filesystems.default');
        $disk = Storage::disk($diskName);
        $tempInputPath = null;
        $tempOutputPath = null;

        if ($diskName === 'public' || $diskName === 'local') {
            $inputPath = $disk->path($clip->video->file_path);
            $disk->makeDirectory('clips');
            $outputRelative = 'clips/' . Str::uuid() . '.mp4';
            $outputPath = $disk->path($outputRelative);
        } else {
            $inputStream = $disk->readStream($clip->video->file_path);
            if (! $inputStream) {
                $clip->update(['status' => Clip::STATUS_ERROR]);
                return;
            }

            $tempInputPath = sys_get_temp_dir() . '/' . Str::uuid() . '.mp4';
            $tempOutputPath = sys_get_temp_dir() . '/' . Str::uuid() . '.mp4';
            file_put_contents($tempInputPath, stream_get_contents($inputStream));
            fclose($inputStream);

            $inputPath = $tempInputPath;
            $outputPath = $tempOutputPath;
            $outputRelative = 'clips/' . Str::uuid() . '.mp4';
        }

        // -c copy で再エンコードなしの高速切り抜き
        $process = new Process([
            'ffmpeg',
            '-i', $inputPath,
            '-ss', (string) $clip->start_seconds,
            '-to', (string) $clip->end_seconds,
            '-c', 'copy',
            '-y',
            $outputPath,
        ]);
        $process->setTimeout($this->timeout);
        $process->run();

        if ($process->isSuccessful() && file_exists($outputPath)) {
            if ($diskName !== 'public' && $diskName !== 'local') {
                $disk->put($outputRelative, file_get_contents($outputPath));
            }

            $clip->update([
                'file_path' => $outputRelative,
                'status' => Clip::STATUS_DONE,
            ]);
        } else {
            Log::error('FFmpeg clip processing failed', [
                'clip_id' => $clip->id,
                'stderr' => $process->getErrorOutput(),
            ]);
            $clip->update(['status' => Clip::STATUS_ERROR]);
        }

        foreach ([$tempInputPath, $tempOutputPath] as $tempPath) {
            if ($tempPath && file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    public function failed(): void
    {
        $this->clip->update(['status' => Clip::STATUS_ERROR]);
    }
}
