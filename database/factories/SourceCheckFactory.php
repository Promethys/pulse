<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Source;
use App\Models\SourceCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SourceCheck>
 */
class SourceCheckFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_id' => Source::factory(),
            'checked_at' => now(),
            'ok' => true,
            'error' => null,
            'suggested_url' => null,
        ];
    }
}
