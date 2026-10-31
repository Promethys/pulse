<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscordGuild;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscordGuild>
 */
class DiscordGuildFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'guild_id' => fake()->numerify('##################'),
            'name' => fake()->company(),
        ];
    }
}
