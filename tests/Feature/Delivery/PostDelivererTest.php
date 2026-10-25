<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Digest\Delivery\Channel;
use App\Digest\Delivery\DeliveryException;
use App\Digest\Delivery\DiscordBotChannel;
use App\Digest\Delivery\DiscordWebhookChannel;
use App\Digest\Delivery\EmailChannel;
use App\Digest\Delivery\InAppChannel;
use App\Digest\Delivery\PostDeliverer;
use App\Digest\Rendering\RenderedPost;
use App\Enums\DeliveryChannelType;
use App\Enums\DeliveryStatus;
use App\Enums\PostKind;
use App\Mail\PostMail;
use App\Models\Delivery;
use App\Models\DeliveryChannel;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class PostDelivererTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://discord.com/api/v10';

    private const WEBHOOK = 'https://discord.com/api/webhooks/webhook-1/private-token';

    /** @var list<array<string, mixed>> */
    private array $calls = [];

    /** @var array<int, Throwable> */
    private array $failures = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Sleep::fake();
        Mail::fake();
        $this->freezeSecond();
        config(['digest.max_attempts' => 4, 'services.discord.token' => 'bot-token']);
    }

    #[DataProvider('drivers')]
    public function test_channel_types_resolve_to_their_channel_implementation(DeliveryChannelType $type, string $driver): void
    {
        $this->assertSame($driver, $type->driver());
        $this->assertInstanceOf(Channel::class, app($type->driver()));
    }

    #[DataProvider('postKinds')]
    public function test_delivers_only_to_the_owners_enabled_channels_receiving_the_post_kind(PostKind $kind, PostKind $otherKind): void
    {
        $this->bindFakeDriver();
        $post = $this->makePost(kind: $kind);
        $first = DeliveryChannel::factory()->inApp()->create(['user_id' => $post->user_id, 'kinds' => [$kind->value]]);
        $second = DeliveryChannel::factory()->inApp()->create(['user_id' => $post->user_id]);
        DeliveryChannel::factory()->inApp()->create(['kinds' => [$kind->value]]);
        DeliveryChannel::factory()->inApp()->disabled()->create(['user_id' => $post->user_id]);
        DeliveryChannel::factory()->inApp()->create(['user_id' => $post->user_id, 'kinds' => [$otherKind->value]]);
        DeliveryChannel::factory()->inApp()->create(['user_id' => $post->user_id, 'kinds' => []]);

        $this->assertSame(0, app(PostDeliverer::class)->deliver($post));

        $this->assertEqualsCanonicalizing([$first->id, $second->id], array_column($this->calls, 'channel_id'));
        $this->assertDatabaseCount('deliveries', 2);
        foreach ($this->calls as $call) {
            $this->assertSame($post->id, $call['post_id']);
            $this->assertSame(DeliveryStatus::Pending, $call['status']);
            $this->assertSame(0, $call['attempts']);
            $this->assertSame(0, $call['progress']);
        }
        foreach (Delivery::query()->get() as $delivery) {
            $this->assertSame(DeliveryStatus::Delivered, $delivery->status);
            $this->assertTrue($delivery->delivered_at->equalTo(now()));
            $this->assertNull($delivery->last_error);
        }
    }

    public function test_failure_counts_are_recorded_and_do_not_stop_later_destinations(): void
    {
        $this->bindFakeDriver();
        $post = $this->makePost();
        $broken = DeliveryChannel::factory()->inApp()->create(['user_id' => $post->user_id]);
        $unexpected = DeliveryChannel::factory()->inApp()->create(['user_id' => $post->user_id]);
        $healthy = DeliveryChannel::factory()->inApp()->create(['user_id' => $post->user_id]);
        $this->failures[$broken->id] = new DeliveryException('Destination unavailable');
        $this->failures[$unexpected->id] = new RuntimeException('Unexpected transport failure');

        $this->assertSame(2, app(PostDeliverer::class)->deliver($post));

        $this->assertCount(3, $this->calls);
        foreach ([$broken, $unexpected] as $channel) {
            $delivery = Delivery::query()->where('delivery_channel_id', $channel->id)->sole();
            $this->assertSame(DeliveryStatus::Failed, $delivery->status);
            $this->assertSame(1, $delivery->attempts);
            $this->assertSame($this->failures[$channel->id]->getMessage(), $delivery->last_error);
            $this->assertNull($delivery->delivered_at);
        }
        $this->assertDatabaseHas('deliveries', ['post_id' => $post->id, 'delivery_channel_id' => $healthy->id, 'status' => 'delivered', 'attempts' => 0, 'last_error' => null]);
    }

    public function test_retries_the_existing_delivery_clears_its_error_and_does_not_send_again_after_success(): void
    {
        $this->bindFakeDriver();
        $post = $this->makePost();
        $channel = DeliveryChannel::factory()->inApp()->create(['user_id' => $post->user_id]);
        $delivery = Delivery::factory()->create([
            'post_id' => $post->id,
            'delivery_channel_id' => $channel->id,
            'status' => DeliveryStatus::Failed,
            'attempts' => 3,
            'last_error' => 'Previous failure',
            'external_id' => 'existing-thread',
            'progress' => 1,
        ]);
        $deliverer = app(PostDeliverer::class);

        $this->assertSame(0, $deliverer->deliver($post));
        $this->assertSame(0, $deliverer->deliver($post));

        $this->assertCount(1, $this->calls);
        $this->assertSame($delivery->id, $this->calls[0]['delivery_id']);
        $this->assertSame(1, $this->calls[0]['progress']);
        $this->assertSame('existing-thread', $this->calls[0]['external_id']);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertSame(DeliveryStatus::Delivered, $delivery->fresh()->status);
        $this->assertSame(3, $delivery->fresh()->attempts);
        $this->assertNull($delivery->fresh()->last_error);
        $this->assertTrue($delivery->fresh()->delivered_at->equalTo(now()));
    }

    #[DataProvider('skippedDeliveries')]
    public function test_delivered_and_exhausted_deliveries_are_unchanged_and_not_called(DeliveryStatus $status, int $attempts): void
    {
        $this->bindFakeDriver();
        $post = $this->makePost();
        $channel = DeliveryChannel::factory()->inApp()->create(['user_id' => $post->user_id]);
        $delivery = Delivery::factory()->create([
            'post_id' => $post->id,
            'delivery_channel_id' => $channel->id,
            'status' => $status,
            'attempts' => $attempts,
            'last_error' => $status === DeliveryStatus::Failed ? 'Keep failure' : null,
            'delivered_at' => $status === DeliveryStatus::Delivered ? now()->subHour() : null,
        ]);
        $before = $delivery->fresh()->getAttributes();

        $this->assertSame(0, app(PostDeliverer::class)->deliver($post));

        $this->assertSame([], $this->calls);
        $this->assertSame($before, $delivery->fresh()->getAttributes());
        $this->assertDatabaseCount('deliveries', 1);
    }

    public function test_a_failure_on_the_last_allowed_attempt_increments_once_and_is_skipped_next_time(): void
    {
        $this->bindFakeDriver();
        config(['digest.max_attempts' => 2]);
        $post = $this->makePost();
        $channel = DeliveryChannel::factory()->inApp()->create(['user_id' => $post->user_id]);
        $delivery = Delivery::factory()->create(['post_id' => $post->id, 'delivery_channel_id' => $channel->id, 'status' => DeliveryStatus::Failed, 'attempts' => 1]);
        $this->failures[$channel->id] = new DeliveryException('Still unavailable');
        $deliverer = app(PostDeliverer::class);

        $this->assertSame(1, $deliverer->deliver($post));
        $this->assertSame(0, $deliverer->deliver($post));

        $this->assertCount(1, $this->calls);
        $this->assertSame(2, $delivery->fresh()->attempts);
        $this->assertSame(DeliveryStatus::Failed, $delivery->fresh()->status);
        $this->assertSame('Still unavailable', $delivery->fresh()->last_error);
    }

    /** @param array<string, mixed>|null $rendered */
    #[DataProvider('emptyRenderedPosts')]
    public function test_missing_rendered_messages_throw_before_creating_or_calling_any_delivery(?array $rendered): void
    {
        $this->bindFakeDriver();
        $post = $this->makePost();
        $post->update(['rendered' => $rendered]);
        DeliveryChannel::factory()->inApp()->create(['user_id' => $post->user_id]);

        try {
            app(PostDeliverer::class)->deliver($post);
            $this->fail('Expected DeliveryException');
        } catch (DeliveryException) {
        }

        $this->assertSame([], $this->calls);
        $this->assertDatabaseCount('deliveries', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_a_post_without_destinations_has_zero_failures_and_creates_no_deliveries(): void
    {
        $this->bindFakeDriver();

        $this->assertSame(0, app(PostDeliverer::class)->deliver($this->makePost()));

        $this->assertSame([], $this->calls);
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_delivers_to_bot_webhook_email_and_in_app_destinations_and_does_not_repeat_them(): void
    {
        $post = $this->makePost();
        DeliveryChannel::factory()->discordBot()->create(['user_id' => $post->user_id, 'settings' => ['guild_id' => 'guild-1', 'channel_id' => 'forum-1']]);
        DeliveryChannel::factory()->discordWebhook()->create(['user_id' => $post->user_id, 'settings' => ['webhook_url' => self::WEBHOOK]]);
        DeliveryChannel::factory()->email()->create(['user_id' => $post->user_id, 'settings' => ['address' => 'reader@example.test']]);
        DeliveryChannel::factory()->inApp()->create(['user_id' => $post->user_id]);
        Http::fake([
            self::API.'/channels/forum-1' => Http::response(['available_tags' => []]),
            self::API.'/channels/forum-1/threads' => Http::response(['id' => 'bot-thread']),
            self::API.'/channels/bot-thread/messages' => Http::response(['id' => 'bot-message']),
            self::WEBHOOK.'?wait=true' => Http::response(['id' => 'webhook-message', 'channel_id' => 'webhook-thread']),
            self::WEBHOOK.'?wait=true&thread_id=webhook-thread' => Http::response(['id' => 'webhook-reply']),
        ]);
        $deliverer = app(PostDeliverer::class);

        $this->assertSame(0, $deliverer->deliver($post));
        $this->assertSame(0, $deliverer->deliver($post));

        $this->assertDatabaseCount('deliveries', 4);
        foreach ($post->deliveries()->get() as $delivery) {
            $this->assertSame(DeliveryStatus::Delivered, $delivery->status);
            $this->assertTrue($delivery->delivered_at->equalTo(now()));
            $this->assertNull($delivery->last_error);
        }
        Http::assertSentCount(5);
        Http::assertSent(fn (Request $r) => $r->url() === self::API.'/channels/forum-1/threads' && $r['message']['content'] === 'First');
        Http::assertSent(fn (Request $r) => $r->url() === self::WEBHOOK.'?wait=true' && $r['content'] === 'First');
        Mail::assertSent(PostMail::class, function (PostMail $mail) {
            $mail->assertHasSubject('Digest');
            $mail->assertSeeInText("First\n\nSecond");

            return $mail->hasTo('reader@example.test');
        });
        Mail::assertSentCount(1);
    }

    #[DataProvider('webhookFailures')]
    public function test_webhook_failures_never_store_the_url_or_token_and_other_destinations_still_deliver(bool $connectionFailure): void
    {
        $post = $this->makePost();
        $channel = DeliveryChannel::factory()->discordWebhook()->create(['user_id' => $post->user_id, 'settings' => ['webhook_url' => self::WEBHOOK]]);
        DeliveryChannel::factory()->email()->create(['user_id' => $post->user_id, 'settings' => ['address' => 'reader@example.test']]);
        Http::fake([self::WEBHOOK.'?wait=true' => $connectionFailure
            ? Http::failedConnection('Connection failed for '.self::WEBHOOK)
            : Http::response(['message' => 'Unknown Webhook'], 404)]);

        $this->assertSame(1, app(PostDeliverer::class)->deliver($post));

        $delivery = Delivery::query()->where('delivery_channel_id', $channel->id)->sole();
        $this->assertSame(DeliveryStatus::Failed, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotEmpty($delivery->last_error);
        $this->assertStringNotContainsString(self::WEBHOOK, $delivery->last_error);
        $this->assertStringNotContainsString('private-token', $delivery->last_error);
        if (! $connectionFailure) {
            $this->assertSame('Discord webhook HTTP 404: Unknown Webhook', $delivery->last_error);
            $this->assertFalse($channel->fresh()->enabled);
        }
        $this->assertSame(1, $post->deliveries()->where('status', DeliveryStatus::Delivered)->count());
        Mail::assertSent(PostMail::class, fn (PostMail $mail) => $mail->hasTo('reader@example.test'));
    }

    /** @return array<string, array{DeliveryChannelType, class-string<Channel>}> */
    public static function drivers(): array
    {
        return [
            'bot' => [DeliveryChannelType::DiscordBot, DiscordBotChannel::class],
            'webhook' => [DeliveryChannelType::DiscordWebhook, DiscordWebhookChannel::class],
            'email' => [DeliveryChannelType::Email, EmailChannel::class],
            'in app' => [DeliveryChannelType::InApp, InAppChannel::class],
        ];
    }

    /** @return array<string, array{PostKind, PostKind}> */
    public static function postKinds(): array
    {
        return ['news' => [PostKind::News, PostKind::Learning], 'learning' => [PostKind::Learning, PostKind::News]];
    }

    /** @return array<string, array{DeliveryStatus, int}> */
    public static function skippedDeliveries(): array
    {
        return ['delivered' => [DeliveryStatus::Delivered, 0], 'at limit' => [DeliveryStatus::Failed, 4], 'over limit' => [DeliveryStatus::Failed, 5], 'pending at limit' => [DeliveryStatus::Pending, 4]];
    }

    /** @return array<string, array{array<string, mixed>|null}> */
    public static function emptyRenderedPosts(): array
    {
        return ['null' => [null], 'missing messages' => [['title' => 'Digest']], 'empty messages' => [(new RenderedPost('Digest', [], []))->toArray()]];
    }

    /** @return array<string, array{bool}> */
    public static function webhookFailures(): array
    {
        return ['HTTP failure' => [false], 'connection failure' => [true]];
    }

    private function makePost(PostKind $kind = PostKind::News): Post
    {
        return Post::factory()->create(['user_id' => User::factory(), 'kind' => $kind, 'rendered' => (new RenderedPost('Digest', ['First', 'Second'], []))->toArray()]);
    }

    private function bindFakeDriver(): void
    {
        $calls = &$this->calls;
        $failures = &$this->failures;
        $this->app->instance(InAppChannel::class, new class($calls, $failures) implements Channel
        {
            /**
             * @param  list<array<string, mixed>>  $calls
             * @param  array<int, Throwable>  $failures
             */
            public function __construct(private array &$calls, private array &$failures) {}

            public function deliver(Post $post, Delivery $delivery): void
            {
                $saved = $delivery->fresh();
                $this->calls[] = [
                    'post_id' => $post->id,
                    'delivery_id' => $saved->id,
                    'channel_id' => $saved->delivery_channel_id,
                    'status' => $saved->status,
                    'attempts' => $saved->attempts,
                    'progress' => $saved->progress,
                    'external_id' => $saved->external_id,
                ];
                if (isset($this->failures[$saved->delivery_channel_id])) {
                    throw $this->failures[$saved->delivery_channel_id];
                }
            }
        });
    }
}
