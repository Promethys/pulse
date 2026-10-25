<?php

declare(strict_types=1);

namespace App\Digest\Delivery;

use App\Digest\Rendering\RenderedPost;
use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryChannel;
use App\Models\Post;
use Illuminate\Contracts\Container\Container;
use Throwable;

class PostDeliverer
{
    public function __construct(private readonly Container $container) {}

    public function deliver(Post $post): int
    {
        $rendered = RenderedPost::fromArray($post->rendered ?? []);
        if ($rendered->messages === []) {
            throw new DeliveryException("Post {$post->id} has no rendered messages");
        }

        $failures = 0;
        $channels = DeliveryChannel::query()->where('user_id', $post->user_id)->receiving($post->kind)->get();

        foreach ($channels as $channel) {
            $delivery = Delivery::query()->firstOrCreate([
                'post_id' => $post->id,
                'delivery_channel_id' => $channel->id,
            ]);

            if ($delivery->status === DeliveryStatus::Delivered || $delivery->attempts >= config('digest.max_attempts')) {
                continue;
            }

            try {
                $driver = $this->container->make($channel->type->driver());
                if (! $driver instanceof Channel) {
                    throw new DeliveryException("No driver for delivery channel type {$channel->type->value}");
                }

                $driver->deliver($post, $delivery);
                $delivery->update(['status' => DeliveryStatus::Delivered, 'delivered_at' => now(), 'last_error' => null]);
            } catch (Throwable $e) {
                $delivery->update([
                    'status' => DeliveryStatus::Failed,
                    'attempts' => $delivery->attempts + 1,
                    'last_error' => $e->getMessage(),
                    'delivered_at' => null,
                ]);
                $failures++;
            }
        }

        return $failures;
    }
}
