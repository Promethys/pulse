<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Models\AiKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiKey>
 */
class AiKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => AiProvider::Gemini,
            'model' => 'gemini-3.8-flash',
            'api_key' => 'fake-ai-key-'.fake()->sha256(),
            'position' => 1,
            'consented_at' => now(),
        ];
    }

    public function provider(AiProvider $provider): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => $provider,
        ]);
    }
}
