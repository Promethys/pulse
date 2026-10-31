<?php

namespace Database\Factories;

use App\Models\LearningTopic;
use App\Models\LearningTopicUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningTopicUsage>
 */
class LearningTopicUsageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'learning_topic_id' => LearningTopic::factory(),
            'used_on' => fake()->date(),
        ];
    }
}
