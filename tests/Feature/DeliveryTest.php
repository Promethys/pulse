<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryChannel;
use App\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_factory_creates_a_pending_delivery_with_zero_attempts_and_progress(): void
    {
        $delivery = Delivery::factory()->create()->refresh();

        $this->assertSame(DeliveryStatus::Pending, $delivery->status);
        $this->assertSame('pending', $delivery->getRawOriginal('status'));
        $this->assertSame(0, $delivery->attempts);
        $this->assertSame(0, $delivery->progress);
        $this->assertNull($delivery->external_id);
        $this->assertNull($delivery->last_error);
        $this->assertNull($delivery->delivered_at);
    }

    public function test_the_database_defaults_to_pending_with_zero_attempts_and_progress(): void
    {
        $post = Post::factory()->create();
        $channel = DeliveryChannel::factory()->create();
        $id = DB::table('deliveries')->insertGetId([
            'post_id' => $post->id,
            'delivery_channel_id' => $channel->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $delivery = Delivery::query()->findOrFail($id);

        $this->assertSame(DeliveryStatus::Pending, $delivery->status);
        $this->assertSame(0, $delivery->attempts);
        $this->assertSame(0, $delivery->progress);
        $this->assertNull($delivery->external_id);
        $this->assertNull($delivery->last_error);
        $this->assertNull($delivery->delivered_at);
    }

    public function test_a_post_cannot_have_two_deliveries_to_the_same_channel(): void
    {
        $delivery = Delivery::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Delivery::factory()->create([
            'post_id' => $delivery->post_id,
            'delivery_channel_id' => $delivery->delivery_channel_id,
        ]);
    }

    public function test_the_same_post_can_have_deliveries_to_two_channels_and_relations_keep_other_posts_separate(): void
    {
        $post = Post::factory()->create();
        $first = Delivery::factory()->create(['post_id' => $post->id]);
        $second = Delivery::factory()->create(['post_id' => $post->id]);
        Delivery::factory()->create();

        $this->assertNotSame($first->delivery_channel_id, $second->delivery_channel_id);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $post->deliveries->modelKeys());
        $this->assertTrue($first->post->is($post));
        $this->assertTrue($second->post->is($post));
    }

    public function test_a_channel_can_have_deliveries_for_two_posts_and_relations_keep_other_channels_separate(): void
    {
        $channel = DeliveryChannel::factory()->create();
        $first = Delivery::factory()->create(['delivery_channel_id' => $channel->id]);
        $second = Delivery::factory()->create(['delivery_channel_id' => $channel->id]);
        Delivery::factory()->create();

        $this->assertNotSame($first->post_id, $second->post_id);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $channel->deliveries->modelKeys());
        $this->assertTrue($first->channel->is($channel));
        $this->assertTrue($second->channel->is($channel));
    }

    public function test_deleting_a_post_deletes_its_deliveries_and_keeps_channels_and_other_deliveries(): void
    {
        $post = Post::factory()->create();
        $deliveries = Delivery::factory()->count(2)->create(['post_id' => $post->id]);
        $channel = $deliveries->first()->channel;
        $other = Delivery::factory()->create(['delivery_channel_id' => $channel->id]);

        $post->delete();

        $this->assertDatabaseMissing('deliveries', ['post_id' => $post->id]);
        $this->assertModelExists($channel);
        $this->assertModelExists($other);
    }

    public function test_deleting_a_channel_deletes_its_deliveries_and_keeps_posts_and_other_deliveries(): void
    {
        $channel = DeliveryChannel::factory()->create();
        $deliveries = Delivery::factory()->count(2)->create(['delivery_channel_id' => $channel->id]);
        $post = $deliveries->first()->post;
        $other = Delivery::factory()->create(['post_id' => $post->id]);

        $channel->delete();

        $this->assertDatabaseMissing('deliveries', ['delivery_channel_id' => $channel->id]);
        $this->assertModelExists($post);
        $this->assertModelExists($other);
    }

    #[DataProvider('deliveryStatuses')]
    public function test_delivery_attributes_can_be_filled_and_are_cast_after_reloading(string $status): void
    {
        $post = Post::factory()->create();
        $channel = DeliveryChannel::factory()->create();
        $delivery = new Delivery;
        $delivery->fill([
            'post_id' => $post->id,
            'delivery_channel_id' => $channel->id,
            'status' => $status,
            'attempts' => '2',
            'progress' => '3',
            'external_id' => 'discord-thread-123',
            'last_error' => 'Delivery interrupted',
            'delivered_at' => '2026-10-25 10:00:00',
        ]);
        $delivery->save();
        $delivery->refresh();

        $this->assertSame($post->id, $delivery->post_id);
        $this->assertSame($channel->id, $delivery->delivery_channel_id);
        $this->assertSame(DeliveryStatus::from($status), $delivery->status);
        $this->assertSame($status, $delivery->getRawOriginal('status'));
        $this->assertSame(2, $delivery->attempts);
        $this->assertSame(3, $delivery->progress);
        $this->assertSame('discord-thread-123', $delivery->external_id);
        $this->assertSame('Delivery interrupted', $delivery->last_error);
        $this->assertInstanceOf(CarbonImmutable::class, $delivery->delivered_at);
        $this->assertSame('2026-10-25 10:00:00', $delivery->delivered_at->format('Y-m-d H:i:s'));
    }

    /** @return array<string, array{string}> */
    public static function deliveryStatuses(): array
    {
        return [
            'pending' => ['pending'],
            'delivered' => ['delivered'],
            'failed' => ['failed'],
        ];
    }
}
