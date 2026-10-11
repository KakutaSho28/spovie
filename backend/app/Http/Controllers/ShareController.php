<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShareLinkRequest;
use App\Http\Resources\ShareLinkResource;
use App\Http\Resources\SharedAnnotationResource;
use App\Models\Annotation;
use App\Services\ShareLinkService;
use Illuminate\Http\JsonResponse;

class ShareController extends Controller
{
    public function __construct(private readonly ShareLinkService $shareLinks) {}

    /** SHARE-01 共有リンク発行 */
    public function store(StoreShareLinkRequest $request, Annotation $annotation): JsonResponse
    {
        $this->authorize('share', $annotation);
        $shareLink = $this->shareLinks->create($annotation, $request->date('expires_at'));

        return (new ShareLinkResource($shareLink))->response()->setStatusCode(201);
    }

    /** SHARE-02 共有リンク取得（認証不要） */
    public function show(string $token): SharedAnnotationResource
    {
        return new SharedAnnotationResource($this->shareLinks->resolve($token));
    }
}
