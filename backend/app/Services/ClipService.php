<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Jobs\ProcessClipJob;
use App\Models\Annotation;
use App\Models\Clip;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClipService
{
    /**
     * 切り抜きジョブを作成して dispatch する。
     * 著作権ポリシー: YouTube動画は切り抜き不可（アップロード動画のみ）。
     */
    public function create(Video $video, ?int $annotationId, string $title, int $startSeconds, int $endSeconds): Clip
    {
        if (! $video->isUpload()) {
            throw new ServiceException('YouTube動画は切り抜き保存できません。直接アップロードした動画のみ対応しています。');
        }

        $clip = Clip::create([
            'video_id' => $video->id,
            'annotation_id' => $annotationId,
            'title' => $title,
            'start_seconds' => $startSeconds,
            'end_seconds' => $endSeconds,
            'status' => Clip::STATUS_PROCESSING,
        ]);

        ProcessClipJob::dispatch($clip);

        return $clip;
    }

    /**
     * ダウンロード可能か検証し、ファイル名を返す（トークン不一致・未完了は 404）。
     */
    public function downloadName(Clip $clip, string $token): string
    {
        $valid = hash_equals((string) $clip->download_token, $token) && $clip->isDone() && $clip->file_path;

        if (! $valid || ! Storage::disk(config('filesystems.default'))->exists($clip->file_path)) {
            throw ServiceException::notFound('クリップが見つかりません');
        }

        return (Str::slug($clip->title, '_') ?: 'clip') . '.mp4';
    }
}
