<?php

namespace Database\Factories;

use App\Models\Clip;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Clip>
 */
class ClipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'video_id' => Video::factory()->upload(),
            'annotation_id' => null,
            'title' => fake()->words(2, true),
            'start_seconds' => 5,
            'end_seconds' => 15,
            'file_path' => null,
            'status' => Clip::STATUS_PROCESSING,
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => Clip::STATUS_DONE,
            'file_path' => 'clips/test.mp4',
        ]);
    }
}
