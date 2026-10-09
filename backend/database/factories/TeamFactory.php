<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'owner_id' => User::factory(),
            'invite_token' => Str::random(64),
        ];
    }

    /**
     * オーナーを owner ロールのメンバーとして登録する
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Team $team) {
            $team->memberships()->create([
                'user_id' => $team->owner_id,
                'role' => TeamMember::ROLE_OWNER,
                'joined_at' => now(),
            ]);
        });
    }
}
