<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SourceType;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'user_id' => null,
            'slug' => $slug,
            'name' => Str::headline($slug),
            'type' => SourceType::Feed,
            'url' => fake()->url().'/feed.xml',
            'tag' => 'devtools',
            'max_items' => 10,
            'position' => fake()->unique()->numberBetween(1, 1000000),
            'enabled' => true,
            'retired_at' => null,
            'is_default' => false,
        ];
    }
}
