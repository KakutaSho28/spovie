<?php

namespace App\Jobs;

use App\Models\Clip;
use App\Services\ClipProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessClipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** FFmpegの最大実行時間（秒） */
    public int $timeout = 600;

    public function __construct(
        public Clip $clip,
    ) {}

    public function handle(ClipProcessingService $processing): void
    {
        $clip = $this->clip->fresh();

        if ($clip) {
            $processing->process($clip, $this->timeout);
        }
    }

    public function failed(): void
    {
        $this->clip->update(['status' => Clip::STATUS_ERROR]);
    }
}
