<?php

namespace Database\Factories;

use App\Models\DigestSettings;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DigestSettings>
 */
class DigestSettingsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'timezone' => 'UTC',
            'profile' => null,
            'news_enabled' => true,
            'news_time' => '09:00',
            'learning_enabled' => true,
            'learning_time' => '09:30',
            'max_items' => 20,
            'onboarded_at' => null,
        ];
    }
}
