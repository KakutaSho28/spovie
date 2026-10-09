<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\Annotation;
use App\Models\ShareLink;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class ShareLinkService
{
    public function create(Annotation $annotation, ?CarbonInterface $expiresAt): ShareLink
    {
        return $annotation->shareLinks()->create([
            'token' => Str::random(64),
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * 認証不要の共有ページ用。存在しない / 期限切れは例外にする。
     */
    public function resolve(string $token): ShareLink
    {
        $shareLink = ShareLink::where('token', $token)->with('annotation.video')->first();

        if (! $shareLink) {
            throw ServiceException::notFound('共有リンクが見つかりません');
        }

        if ($shareLink->isExpired()) {
            throw new ServiceException('この共有リンクは有効期限が切れています', 410);
        }

        return $shareLink;
    }
}
