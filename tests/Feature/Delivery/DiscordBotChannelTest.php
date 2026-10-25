<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Digest\Delivery\Channel;
use App\Digest\Delivery\DiscordBotChannel;
use App\Digest\Discord\DiscordException;
use App\Digest\Rendering\RenderedPost;
use App\Enums\PostKind;
use App\Models\Delivery;
use App\Models\DeliveryChannel;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DiscordBotChannelTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://discord.com/api/v10';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Sleep::fake();
        config(['services.discord.token' => 'bot-token']);
    }

    public function test_creates_a_thread_in_the_destinations_forum_and_saves_each_message_before_sending_the_next(): void
    {
        Tag::query()->create(['slug' => 'ai', 'name' => 'AI']);
        [$post, $delivery] = $this->makeDelivery(['First', 'Second', 'Third'], ['ai']);
        Http::fake(function (Request $request) use ($delivery) {
            if ($request->method() === 'GET') {
                return Http::response(['available_tags' => [['id' => '11', 'name' => 'AI']]]);
            }
            if ($request->url() === self::API.'/channels/forum-1/threads') {
                return Http::response(['id' => 'thread-1']);
            }
            $saved = $delivery->fresh();
            $this->assertSame('thread-1', $saved->external_id);
            $this->assertSame($request['content'] === 'Second' ? 1 : 2, $saved->progress);

            return Http::response(['id' => 'message-1']);
        });

        $driver = app(DiscordBotChannel::class);
        $this->assertInstanceOf(Channel::class, $driver);
        $driver->deliver($post, $delivery);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::API.'/channels/forum-1/threads'
            && $r->hasHeader('Authorization', 'Bot bot-token')
            && $r->hasHeader('Accept', 'application/json')
            && $r->data() == ['name' => 'Digest', 'message' => ['content' => 'First', 'allowed_mentions' => ['parse' => []]], 'applied_tags' => ['11']]);
        foreach (['Second', 'Third'] as $content) {
            Http::assertSent(fn (Request $r) => $r->method() === 'POST'
                && $r->url() === self::API.'/channels/thread-1/messages'
                && $r->hasHeader('Authorization', 'Bot bot-token')
                && $r->data() == ['content' => $content, 'allowed_mentions' => ['parse' => []]]);
        }
        Http::assertSentCount(4);
        $this->assertSame(3, $delivery->fresh()->progress);
    }

    public function test_a_single_message_title_is_cut_to_one_hundred_characters(): void
    {
        [$post, $delivery] = $this->makeDelivery(['Only'], title: str_repeat('é', 101));
        Http::fake([
            self::API.'/channels/forum-1' => Http::response(['available_tags' => []]),
            self::API.'/channels/forum-1/threads' => Http::response(['id' => 'thread-1']),
        ]);

        app(DiscordBotChannel::class)->deliver($post, $delivery);

        Http::assertSent(fn (Request $r) => $r->url() === self::API.'/channels/forum-1/threads' && $r['name'] === str_repeat('é', 100));
        Http::assertSentCount(2);
        $this->assertSame('thread-1', $delivery->fresh()->external_id);
        $this->assertSame(1, $delivery->fresh()->progress);
    }

    public function test_resumes_without_reading_tags_or_creating_a_duplicate_thread(): void
    {
        [$post, $delivery] = $this->makeDelivery(['First', 'Second', 'Third']);
        $delivery->update(['external_id' => 'existing-thread', 'progress' => 2]);
        Http::fake([self::API.'/channels/existing-thread/messages' => Http::response(['id' => 'message-3'])]);

        app(DiscordBotChannel::class)->deliver($post, $delivery);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::API.'/channels/existing-thread/messages'
            && $r['content'] === 'Third');
        $this->assertSame(3, $delivery->fresh()->progress);
    }

    public function test_a_fully_sent_delivery_sends_nothing(): void
    {
        [$post, $delivery] = $this->makeDelivery(['First']);
        $delivery->update(['external_id' => 'existing-thread', 'progress' => 1]);

        app(DiscordBotChannel::class)->deliver($post, $delivery);

        Http::assertNothingSent();
        $this->assertSame(1, $delivery->fresh()->progress);
    }

    public function test_a_forbidden_tag_lookup_disables_the_channel_before_any_thread_is_created(): void
    {
        [$post, $delivery] = $this->makeDelivery(['First']);
        Http::fake([self::API.'/channels/forum-1' => Http::response(['message' => 'Missing Access'], 403)]);

        try {
            app(DiscordBotChannel::class)->deliver($post, $delivery);
            $this->fail('Expected DiscordException');
        } catch (DiscordException $e) {
            $this->assertSame(403, $e->status);
        }

        $this->assertFalse($delivery->channel->fresh()->enabled);
        $this->assertNull($delivery->fresh()->external_id);
        $this->assertSame(0, $delivery->fresh()->progress);
        Http::assertSentCount(1);
    }

    public function test_connection_failures_do_not_disable_the_channel_or_advance_progress(): void
    {
        [$post, $delivery] = $this->makeDelivery(['First', 'Second']);
        $delivery->update(['external_id' => 'thread-1', 'progress' => 1]);
        Http::fake([self::API.'/channels/thread-1/messages' => Http::failedConnection('Connection refused')]);

        try {
            app(DiscordBotChannel::class)->deliver($post, $delivery);
            $this->fail('Expected DiscordException');
        } catch (DiscordException $e) {
            $this->assertNull($e->status);
        }

        $this->assertTrue($delivery->channel->fresh()->enabled);
        $this->assertSame('thread-1', $delivery->fresh()->external_id);
        $this->assertSame(1, $delivery->fresh()->progress);
    }

    public function test_a_failed_reply_preserves_progress_and_retry_sends_only_the_remaining_message(): void
    {
        [$post, $delivery] = $this->makeDelivery(['First', 'Second', 'Third']);
        Http::fake([
            self::API.'/channels/forum-1' => Http::response(['available_tags' => []]),
            self::API.'/channels/forum-1/threads' => Http::response(['id' => 'thread-1']),
            self::API.'/channels/thread-1/messages' => Http::sequence()->push(['id' => 'message-2'])->push(['message' => 'Unavailable'], 500)->push(['id' => 'message-3']),
        ]);
        $driver = app(DiscordBotChannel::class);

        try {
            $driver->deliver($post, $delivery);
            $this->fail('Expected DiscordException');
        } catch (DiscordException $e) {
            $this->assertStringContainsString('Unavailable', $e->getMessage());
        }
        $this->assertSame('thread-1', $delivery->fresh()->external_id);
        $this->assertSame(2, $delivery->fresh()->progress);
        $this->assertTrue($delivery->channel->fresh()->enabled);

        $driver->deliver($post, $delivery->fresh());

        $this->assertSame(3, $delivery->fresh()->progress);
        $this->assertCount(2, Http::recorded(fn (Request $r) => $r->url() === self::API.'/channels/thread-1/messages' && $r['content'] === 'Third'));
        $this->assertCount(1, Http::recorded(fn (Request $r) => $r->url() === self::API.'/channels/forum-1/threads'));
    }

    #[DataProvider('failureStages')]
    public function test_only_forbidden_errors_disable_the_channel(int $status, bool $resume): void
    {
        [$post, $delivery] = $this->makeDelivery(['First', 'Second']);
        if ($resume) {
            $delivery->update(['external_id' => 'thread-1', 'progress' => 1]);
        }
        Http::fake([
            self::API.'/channels/forum-1' => Http::response(['available_tags' => []]),
            self::API.'/channels/forum-1/threads' => Http::response(['message' => 'No access'], $status),
            self::API.'/channels/thread-1/messages' => Http::response(['message' => 'No access'], $status),
        ]);

        try {
            app(DiscordBotChannel::class)->deliver($post, $delivery);
            $this->fail('Expected DiscordException');
        } catch (DiscordException $e) {
            $this->assertSame($status, $e->status);
        }

        $this->assertSame($status !== 403, $delivery->channel->fresh()->enabled);
        $this->assertSame($resume ? 1 : 0, $delivery->fresh()->progress);
        $this->assertSame($resume ? 'thread-1' : null, $delivery->fresh()->external_id);
    }

    /** @return array<string, array{int, bool}> */
    public static function failureStages(): array
    {
        return ['forbidden thread' => [403, false], 'forbidden reply' => [403, true], 'server error' => [500, false]];
    }

    /**
     * @param  list<string>  $messages
     * @param  list<string>  $slugs
     * @return array{Post, Delivery}
     */
    private function makeDelivery(array $messages, array $slugs = [], string $title = 'Digest'): array
    {
        $channel = DeliveryChannel::factory()->discordBot()->create(['settings' => ['guild_id' => 'guild-1', 'channel_id' => 'forum-1']]);
        $post = Post::factory()->create(['user_id' => $channel->user_id, 'kind' => PostKind::News, 'rendered' => (new RenderedPost($title, $messages, $slugs))->toArray()]);

        return [$post, Delivery::factory()->create(['post_id' => $post->id, 'delivery_channel_id' => $channel->id])];
    }
}
