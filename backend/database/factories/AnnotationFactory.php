<?php

namespace Database\Factories;

use App\Models\Annotation;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Annotation>
 */
class AnnotationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'video_id' => Video::factory(),
            'start_seconds' => 10,
            'end_seconds' => 20,
            'canvas_data' => ['canvas_width' => 1280, 'canvas_height' => 720, 'objects' => []],
            'comment' => fake()->sentence(),
        ];
    }
}
