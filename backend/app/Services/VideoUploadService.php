<?php

namespace App\Services;

use App\Models\User;
use App\Models\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class VideoUploadService
{
    public function __construct(
        private readonly VideoService $videos,
        private readonly MediaStorageService $media,
    ) {}

    public function store(User $user, string $title, UploadedFile $file, ?int $teamId): Video
    {
        $this->videos->assertCanUseTeam($user, $teamId);

        $path = $this->media->disk()->putFileAs('videos', $file, Str::uuid() . '.mp4');

        return $user->videos()->create([
            'team_id' => $teamId,
            'type' => Video::TYPE_UPLOAD,
            'file_path' => $path,
            'title' => $title,
        ]);
    }
}
