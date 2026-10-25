<?php

namespace Database\Factories;

use App\Enums\DeliveryChannelType;
use App\Enums\PostKind;
use App\Models\DeliveryChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryChannel>
 */
class DeliveryChannelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => DeliveryChannelType::InApp,
            'name' => 'In the app',
            'kinds' => [PostKind::News->value, PostKind::Learning->value],
            'enabled' => true,
            'settings' => null,
            'verified_at' => null,
        ];
    }

    public function discordBot(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DeliveryChannelType::DiscordBot,
            'name' => 'Discord bot',
            'settings' => [
                'guild_id' => fake()->numerify('##################'),
                'channel_id' => fake()->numerify('##################'),
            ],
        ]);
    }

    public function discordWebhook(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DeliveryChannelType::DiscordWebhook,
            'name' => 'Discord webhook',
            'settings' => [
                'webhook_url' => 'https://discord.com/api/webhooks/'.fake()->numerify('##################').'/'.fake()->sha256(),
            ],
        ]);
    }

    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DeliveryChannelType::Email,
            'name' => 'Email',
            'settings' => ['address' => fake()->safeEmail()],
        ]);
    }

    public function inApp(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DeliveryChannelType::InApp,
            'name' => 'In the app',
            'settings' => null,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }
}
