<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Discord\DiscordException;
use App\Digest\Discord\ForumPublisher;
use App\Digest\Rendering\RenderedPost;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ForumPublisherTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://discord.com/api/v10';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Sleep::fake();
        config(['services.discord.token' => 'bot-token']);
        Tag::query()->create(['slug' => 'ai', 'name' => 'AI']);
        Tag::query()->create(['slug' => 'laravel', 'name' => 'Laravel']);
    }

    public function test_creates_thread_with_tags_and_replies(): void
    {
        $this->fakeDiscord(availableTags: [['id' => '11', 'name' => 'AI']]);
        $post = $this->makePost(['Part 1', 'Part 2', 'Part 3'], ['ai', 'laravel']);

        app(ForumPublisher::class)->publish($post, 'forum-1');

        $post->refresh();
        $this->assertSame(PostStatus::Posted, $post->status);
        $this->assertSame('thread-1', $post->discord_thread_id);
        $this->assertSame(3, $post->messages_sent);
        $this->assertNotNull($post->posted_at);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::API.'/channels/forum-1/threads'
            && $r->hasHeader('Authorization', 'Bot bot-token')
            && $r['name'] === '📰 News digest — Fri 16 Oct'
            && $r['message']['content'] === 'Part 1'
            && $r['applied_tags'] === ['11', '12']);
        Http::assertSent(fn (Request $r) => $r->url() === self::API.'/channels/thread-1/messages' && $r['content'] === 'Part 3');
    }

    public function test_matches_tags_by_name_in_each_forum(): void
    {
        Http::fake(function (Request $request) {
            return match (true) {
                $request->method() === 'GET' && str_ends_with($request->url(), '/channels/forum-news') => Http::response(['available_tags' => [['id' => '11', 'name' => 'AI']]]),
                $request->method() === 'GET' && str_ends_with($request->url(), '/channels/forum-learning') => Http::response(['available_tags' => [['id' => '21', 'name' => 'AI']]]),
                str_ends_with($request->url(), '/threads') => Http::response(['id' => 'thread-'.(str_contains($request->url(), 'news') ? 'n' : 'l')]),
                default => Http::response('unexpected', 404),
            };
        });

        app(ForumPublisher::class)->publish($this->makePost(['News'], ['ai']), 'forum-news');
        app(ForumPublisher::class)->publish($this->makePost(['Learning'], ['ai'], kind: PostKind::Learning), 'forum-learning');
        app(ForumPublisher::class)->publish($this->makePost(['News again'], ['ai'], date: '2026-10-17'), 'forum-news');

        Http::assertNotSent(fn (Request $r) => $r->method() === 'PATCH');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/channels/forum-news/threads') && $r['applied_tags'] === ['11'] && $r['message']['content'] === 'News again');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/channels/forum-learning/threads') && $r['applied_tags'] === ['21']);
    }

    public function test_success_clears_the_previous_error(): void
    {
        $this->fakeDiscord(availableTags: []);
        $post = $this->makePost(['Only part'], []);
        $post->update(['status' => PostStatus::Failed, 'attempts' => 1, 'last_error' => 'Discord HTTP 403: Missing Access']);

        app(ForumPublisher::class)->publish($post, 'forum-1');

        $post->refresh();
        $this->assertSame(PostStatus::Posted, $post->status);
        $this->assertNull($post->last_error);
        $this->assertSame(1, $post->attempts);
    }

    public function test_tags_no_longer_store_a_discord_id(): void
    {
        $this->assertFalse(Schema::hasColumn('tags', 'discord_tag_id'));
    }

    public function test_creates_missing_forum_tags_up_to_twenty(): void
    {
        $existing = array_map(fn (int $i) => ['id' => (string) (100 + $i), 'name' => "Tag {$i}"], range(1, 19));
        $this->fakeDiscord(availableTags: $existing);
        $post = $this->makePost(['Only part'], ['ai', 'laravel']);

        app(ForumPublisher::class)->publish($post, 'forum-1');

        Http::assertSent(function (Request $r) {
            $tags = $r->method() === 'PATCH' ? $r['available_tags'] : [];

            return count($tags) === 20 && $tags[19] === ['name' => 'AI'];
        });
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/threads') && $r['applied_tags'] === ['12']);
        $this->assertSame(PostStatus::Posted, $post->refresh()->status);
    }

    public function test_resumes_a_half_posted_thread(): void
    {
        $this->fakeDiscord(availableTags: [['id' => '11', 'name' => 'AI']]);
        $post = $this->makePost(['Part 1', 'Part 2', 'Part 3'], ['ai']);
        $post->update(['discord_thread_id' => 'thread-9', 'messages_sent' => 1]);

        app(ForumPublisher::class)->publish($post, 'forum-1');

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/threads'));
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r->url() === self::API.'/channels/thread-9/messages' && $r['content'] === 'Part 2');
        $this->assertSame(3, $post->refresh()->messages_sent);
    }

    public function test_saves_progress_when_a_reply_fails(): void
    {
        $this->fakeDiscord(availableTags: [], messageStatus: 500);
        $post = $this->makePost(['Part 1', 'Part 2'], []);

        try {
            app(ForumPublisher::class)->publish($post, 'forum-1');
            $this->fail('Expected DiscordException');
        } catch (DiscordException) {
        }

        $post->refresh();
        $this->assertSame('thread-1', $post->discord_thread_id);
        $this->assertSame(1, $post->messages_sent);
        $this->assertNotSame(PostStatus::Posted, $post->status);
    }

    public function test_disables_mentions(): void
    {
        $this->fakeDiscord(availableTags: []);

        app(ForumPublisher::class)->publish($this->makePost(['A', 'B'], []), 'forum-1');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/threads') && $r['message']['allowed_mentions'] === ['parse' => []]);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/messages') && $r['allowed_mentions'] === ['parse' => []]);
    }

    public function test_retries_once_on_429(): void
    {
        Http::fake([
            self::API.'/channels/forum-1' => Http::response(['id' => 'forum-1', 'available_tags' => []]),
            self::API.'/channels/forum-1/threads' => Http::sequence()
                ->push(['message' => 'You are being rate limited.', 'retry_after' => 1.5, 'global' => false], 429)
                ->push(['id' => 'thread-1']),
        ]);

        app(ForumPublisher::class)->publish($this->makePost(['Only part'], []), 'forum-1');

        Sleep::assertSlept(fn ($duration) => (int) $duration->totalMilliseconds === 1500, times: 1);
    }

    public function test_long_titles_and_tag_names_are_cut_to_discord_limits(): void
    {
        Tag::query()->create(['slug' => 'very-long', 'name' => 'A Very Long Tag Display Name']);
        $this->fakeDiscord(availableTags: []);
        $post = $this->makePost(['Only part'], ['very-long'], title: str_repeat('t', 150));

        app(ForumPublisher::class)->publish($post, 'forum-1');

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && mb_strlen($r['available_tags'][0]['name']) <= 20);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/threads') && mb_strlen($r['name']) <= 100);
    }

    public function test_missing_token_throws(): void
    {
        config(['services.discord.token' => null]);

        $this->expectException(DiscordException::class);
        $this->expectExceptionMessage('DISCORD_BOT_TOKEN');

        app(ForumPublisher::class)->publish($this->makePost(['A'], []), 'forum-1');
    }

    /**
     * @param  list<array{id: string, name: string}>  $availableTags
     */
    private function fakeDiscord(array $availableTags, int $messageStatus = 200): void
    {
        $nextId = 12;
        Http::fake(function (Request $request) use (&$availableTags, &$nextId, $messageStatus) {
            $url = $request->url();

            return match (true) {
                $request->method() === 'GET' && str_ends_with($url, '/channels/forum-1') => Http::response(['id' => 'forum-1', 'available_tags' => $availableTags]),
                $request->method() === 'PATCH' => Http::response(['id' => 'forum-1', 'available_tags' => $availableTags = array_map(
                    function (array $tag) use (&$nextId) {
                        return isset($tag['id']) ? $tag : ['id' => (string) $nextId++, 'name' => $tag['name']];
                    },
                    $request['available_tags'],
                )]),
                str_ends_with($url, '/threads') => Http::response(['id' => 'thread-1']),
                str_ends_with($url, '/messages') => Http::response(['id' => 'msg'], $messageStatus),
                default => Http::response('unexpected', 404),
            };
        });
    }

    /**
     * @param  list<string>  $messages
     * @param  list<string>  $tagSlugs
     */
    private function makePost(array $messages, array $tagSlugs, string $title = '📰 News digest — Fri 16 Oct', PostKind $kind = PostKind::News, string $date = '2026-10-16'): Post
    {
        return Post::query()->create([
            'date' => $date,
            'kind' => $kind,
            'status' => PostStatus::Generated,
            'rendered' => (new RenderedPost($title, $messages, $tagSlugs))->toArray(),
        ]);
    }
}
