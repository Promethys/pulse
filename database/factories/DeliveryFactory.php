<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryChannel;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'delivery_channel_id' => DeliveryChannel::factory(),
            'status' => DeliveryStatus::Pending,
            'attempts' => 0,
            'progress' => 0,
            'external_id' => null,
            'last_error' => null,
            'delivered_at' => null,
        ];
    }
}
