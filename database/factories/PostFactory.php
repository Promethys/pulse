<?php

namespace Database\Factories;

use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->date(),
            'kind' => PostKind::News,
            'status' => PostStatus::Pending,
        ];
    }
}
