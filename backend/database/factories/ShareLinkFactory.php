<?php

namespace Database\Factories;

use App\Models\Annotation;
use App\Models\ShareLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShareLink>
 */
class ShareLinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'annotation_id' => Annotation::factory(),
            'token' => Str::random(64),
            'expires_at' => null,
        ];
    }
}
