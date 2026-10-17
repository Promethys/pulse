<?php

namespace Database\Factories;

use App\Models\LearningTopic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningTopic>
 */
class LearningTopicFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->words(3, true),
            'category' => fake()->randomElement(['architecture', 'databases', 'testing', 'performance']),
            'last_used_on' => null,
        ];
    }
}
