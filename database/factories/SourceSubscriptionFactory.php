<?php

namespace Database\Factories;

use App\Models\Source;
use App\Models\SourceSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SourceSubscription>
 */
class SourceSubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source_id' => Source::factory(),
            'enabled' => true,
        ];
    }
}
