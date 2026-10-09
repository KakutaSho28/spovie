<?php

namespace App\Policies;

use App\Models\Annotation;
use App\Models\User;

/**
 * アノテーションの権限は所属する動画の閲覧権限に従う
 */
class AnnotationPolicy
{
    public function view(User $user, Annotation $annotation): bool
    {
        return $annotation->video->canBeAccessedBy($user);
    }

    public function delete(User $user, Annotation $annotation): bool
    {
        return $this->view($user, $annotation);
    }

    public function share(User $user, Annotation $annotation): bool
    {
        return $this->view($user, $annotation);
    }
}
