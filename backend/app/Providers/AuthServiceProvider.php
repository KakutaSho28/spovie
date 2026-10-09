<?php

namespace App\Providers;

use App\Models\Annotation;
use App\Models\Clip;
use App\Models\Comment;
use App\Models\Team;
use App\Models\Video;
use App\Policies\AnnotationPolicy;
use App\Policies\ClipPolicy;
use App\Policies\CommentPolicy;
use App\Policies\TeamPolicy;
use App\Policies\VideoPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Video::class => VideoPolicy::class,
        Annotation::class => AnnotationPolicy::class,
        Clip::class => ClipPolicy::class,
        Comment::class => CommentPolicy::class,
        Team::class => TeamPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
