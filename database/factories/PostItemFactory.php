<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Post;
use App\Models\PostItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostItem>
 */
class PostItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'item_id' => Item::factory(),
            'title' => fake()->sentence(6),
            'summary' => null,
            'position' => 1,
        ];
    }
}
