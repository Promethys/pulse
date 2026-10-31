<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Digest\Delivery\Channel;
use App\Digest\Delivery\InAppChannel;
use App\Digest\Rendering\RenderedPost;
use App\Enums\PostKind;
use App\Models\Delivery;
use App\Models\DeliveryChannel;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InAppChannelTest extends TestCase
{
    use RefreshDatabase;

    /** @param list<string> $messages */
    #[DataProvider('deliveryStates')]
    public function test_delivery_is_a_no_op_even_with_no_messages_or_existing_progress(array $messages, int $progress): void
    {
        Http::preventStrayRequests();
        Mail::fake();
        $channel = DeliveryChannel::factory()->inApp()->create();
        $post = Post::factory()->create(['user_id' => $channel->user_id, 'kind' => PostKind::News, 'rendered' => (new RenderedPost('Digest', $messages, []))->toArray()]);
        $delivery = Delivery::factory()->create(['post_id' => $post->id, 'delivery_channel_id' => $channel->id, 'progress' => $progress]);
        $before = $delivery->fresh()->getAttributes();
        $driver = app(InAppChannel::class);
        $this->assertInstanceOf(Channel::class, $driver);

        $driver->deliver($post, $delivery);

        $this->assertSame($before, $delivery->fresh()->getAttributes());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    /** @return array<string, array{list<string>, int}> */
    public static function deliveryStates(): array
    {
        return ['single' => [['First'], 0], 'multiple' => [['First', 'Second'], 0], 'resume' => [['First', 'Second'], 1], 'empty' => [[], 0]];
    }
}
