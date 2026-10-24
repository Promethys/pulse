<?php

namespace Database\Factories;

use App\Enums\InterestCategory;
use App\Enums\InterestStatus;
use App\Models\Interest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Interest>
 */
class InterestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'slug' => $slug,
            'name' => Str::headline($slug),
            'category' => InterestCategory::Life,
            'icon' => 'heart',
            'position' => fake()->numberBetween(1, 100),
            'has_learning' => false,
            'status' => InterestStatus::Draft,
        ];
    }
}
