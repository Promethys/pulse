<?php

namespace Database\Factories;

use App\Enums\ItemStatus;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = 'https://example.com/'.fake()->unique()->slug();

        return [
            'source_slug' => 'laravel-news',
            'url' => $url,
            'url_normalized' => $url,
            'title' => fake()->sentence(6),
            'excerpt' => fake()->paragraph(),
            'links' => [$url],
            'published_at' => now(),
            'status' => ItemStatus::Candidate,
        ];
    }
}
