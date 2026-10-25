<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Digest\Delivery\Channel;
use App\Digest\Delivery\DeliveryException;
use App\Digest\Delivery\DiscordWebhookChannel;
use App\Digest\Rendering\RenderedPost;
use App\Enums\PostKind;
use App\Models\Delivery;
use App\Models\DeliveryChannel;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DiscordWebhookChannelTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://discord.com/api/webhooks/webhook-1/private-token';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Sleep::fake();
    }

    public function test_creates_a_thread_without_tags_and_saves_progress_before_each_reply(): void
    {
        [$post, $delivery] = $this->makeDelivery(['First', 'Second', 'Third']);
        Http::fake(function (Request $r) use ($delivery) {
            if ($r->url() === self::WEBHOOK.'?wait=true') {
                return Http::response(['id' => 'message-1', 'channel_id' => 'thread-1']);
            }
            $this->assertSame('thread-1', $delivery->fresh()->external_id);
            $this->assertSame($r['content'] === 'Second' ? 1 : 2, $delivery->fresh()->progress);

            return Http::response(['id' => 'message-next', 'channel_id' => 'thread-1']);
        });
        $driver = app(DiscordWebhookChannel::class);
        $this->assertInstanceOf(Channel::class, $driver);

        $driver->deliver($post, $delivery);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::WEBHOOK.'?wait=true'
            && $r->hasHeader('Content-Type', 'application/json')
            && ! $r->hasHeader('Authorization')
            && $r->data() == ['thread_name' => 'Digest', 'content' => 'First', 'allowed_mentions' => ['parse' => []]]);
        foreach (['Second', 'Third'] as $content) {
            Http::assertSent(fn (Request $r) => $r->method() === 'POST'
                && $r->url() === self::WEBHOOK.'?wait=true&thread_id=thread-1'
                && $r->hasHeader('Content-Type', 'application/json')
                && ! $r->hasHeader('Authorization')
                && $r->data() == ['content' => $content, 'allowed_mentions' => ['parse' => []]]);
        }
        Http::assertSentCount(3);
        $this->assertSame('thread-1', $delivery->fresh()->external_id);
        $this->assertSame(3, $delivery->fresh()->progress);
    }

    public function test_single_message_cuts_the_title_at_one_hundred_characters(): void
    {
        [$post, $delivery] = $this->makeDelivery(['Only'], str_repeat('é', 101));
        Http::fake([self::WEBHOOK.'?wait=true' => Http::response(['id' => 'message-1', 'channel_id' => 'thread-1'])]);

        app(DiscordWebhookChannel::class)->deliver($post, $delivery);

        Http::assertSent(fn (Request $r) => $r['thread_name'] === str_repeat('é', 100));
        Http::assertSentCount(1);
        $this->assertSame(1, $delivery->fresh()->progress);
    }

    public function test_resumes_at_saved_progress_without_creating_a_thread(): void
    {
        [$post, $delivery] = $this->makeDelivery(['First', 'Second', 'Third']);
        $delivery->update(['external_id' => 'existing-thread', 'progress' => 2]);
        Http::fake([self::WEBHOOK.'?wait=true&thread_id=existing-thread' => Http::response(['id' => 'message-3'])]);

        app(DiscordWebhookChannel::class)->deliver($post, $delivery);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->url() === self::WEBHOOK.'?wait=true&thread_id=existing-thread'
            && $r['content'] === 'Third' && ! isset($r['thread_name']));
        $this->assertSame(3, $delivery->fresh()->progress);
    }

    public function test_a_fully_sent_delivery_sends_nothing(): void
    {
        [$post, $delivery] = $this->makeDelivery(['First']);
        $delivery->update(['external_id' => 'thread-1', 'progress' => 1]);

        app(DiscordWebhookChannel::class)->deliver($post, $delivery);

        Http::assertNothingSent();
        $this->assertSame(1, $delivery->fresh()->progress);
    }

    public function test_a_reply_failure_preserves_progress_and_retry_does_not_duplicate_messages(): void
    {
        [$post, $delivery] = $this->makeDelivery(['First', 'Second', 'Third']);
        Http::fake([
            self::WEBHOOK.'?wait=true' => Http::response(['id' => 'message-1', 'channel_id' => 'thread-1']),
            self::WEBHOOK.'?wait=true&thread_id=thread-1' => Http::sequence()->push(['id' => 'message-2'])->push(['message' => 'Unavailable'], 500)->push(['id' => 'message-3']),
        ]);
        $driver = app(DiscordWebhookChannel::class);

        try {
            $driver->deliver($post, $delivery);
            $this->fail('Expected DeliveryException');
        } catch (DeliveryException $e) {
            $this->assertSame('Discord webhook HTTP 500: Unavailable', $e->getMessage());
        }
        $this->assertSame('thread-1', $delivery->fresh()->external_id);
        $this->assertSame(2, $delivery->fresh()->progress);

        $driver->deliver($post, $delivery->fresh());

        $this->assertSame(3, $delivery->fresh()->progress);
        $this->assertCount(1, Http::recorded(fn (Request $r) => $r->url() === self::WEBHOOK.'?wait=true'));
        $this->assertCount(1, Http::recorded(fn (Request $r) => $r['content'] === 'Second'));
        $this->assertCount(2, Http::recorded(fn (Request $r) => $r['content'] === 'Third'));
    }

    #[DataProvider('httpFailures')]
    public function test_http_errors_are_safe_and_only_deleted_webhooks_are_disabled(int $status, bool $resume): void
    {
        [$post, $delivery] = $this->makeDelivery(['First', 'Second']);
        if ($resume) {
            $delivery->update(['external_id' => 'thread-1', 'progress' => 1]);
        }
        $url = self::WEBHOOK.($resume ? '?wait=true&thread_id=thread-1' : '?wait=true');
        Http::fake([$url => Http::response(['message' => 'Unknown Webhook'], $status)]);

        try {
            app(DiscordWebhookChannel::class)->deliver($post, $delivery);
            $this->fail('Expected DeliveryException');
        } catch (DeliveryException $e) {
            $this->assertSame("Discord webhook HTTP {$status}: Unknown Webhook", $e->getMessage());
            $this->assertStringNotContainsString(self::WEBHOOK, $e->getMessage());
            $this->assertStringNotContainsString('private-token', $e->getMessage());
        }

        $this->assertSame($status !== 404, $delivery->channel->fresh()->enabled);
        $this->assertSame($resume ? 1 : 0, $delivery->fresh()->progress);
        $this->assertSame($resume ? 'thread-1' : null, $delivery->fresh()->external_id);
    }

    public function test_connection_errors_never_expose_the_webhook_secret(): void
    {
        [$post, $delivery] = $this->makeDelivery(['First']);
        Http::fake([self::WEBHOOK.'?wait=true' => Http::failedConnection('Could not connect to '.self::WEBHOOK)]);

        try {
            app(DiscordWebhookChannel::class)->deliver($post, $delivery);
            $this->fail('Expected DeliveryException');
        } catch (DeliveryException $e) {
            $this->assertStringNotContainsString(self::WEBHOOK, $e->getMessage());
            $this->assertStringNotContainsString('private-token', $e->getMessage());
        }

        $this->assertSame(0, $delivery->fresh()->progress);
        $this->assertNull($delivery->fresh()->external_id);
        $this->assertTrue($delivery->channel->fresh()->enabled);
    }

    /** @return array<string, array{int, bool}> */
    public static function httpFailures(): array
    {
        return ['deleted thread' => [404, false], 'deleted reply' => [404, true], 'forbidden' => [403, false], 'server error' => [500, false]];
    }

    /**
     * @param  list<string>  $messages
     * @return array{Post, Delivery}
     */
    private function makeDelivery(array $messages, string $title = 'Digest'): array
    {
        $channel = DeliveryChannel::factory()->discordWebhook()->create(['settings' => ['webhook_url' => self::WEBHOOK]]);
        $post = Post::factory()->create(['user_id' => $channel->user_id, 'kind' => PostKind::News, 'rendered' => (new RenderedPost($title, $messages, ['ai']))->toArray()]);

        return [$post, Delivery::factory()->create(['post_id' => $post->id, 'delivery_channel_id' => $channel->id])];
    }
}
