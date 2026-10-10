<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'team_id' => null,
            'type' => Video::TYPE_YOUTUBE,
            'youtube_video_id' => Str::random(11),
            'file_path' => null,
            'title' => fake()->sentence(3),
        ];
    }

    public function upload(): static
    {
        return $this->state(fn () => [
            'type' => Video::TYPE_UPLOAD,
            'youtube_video_id' => null,
            'file_path' => 'videos/' . Str::random(16) . '.mp4',
        ]);
    }
}
