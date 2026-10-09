<?php

namespace App\Services;

use App\Models\Annotation;
use App\Models\Video;
use Illuminate\Database\Eloquent\Collection;

class AnnotationService
{
    /**
     * @return Collection<int, Annotation>
     */
    public function listFor(Video $video): Collection
    {
        return $video->annotations()->withCount('comments')->orderByDesc('created_at')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Video $video, array $data): Annotation
    {
        return $video->annotations()->create($data);
    }

    public function delete(Annotation $annotation): void
    {
        $annotation->delete();
    }
}
