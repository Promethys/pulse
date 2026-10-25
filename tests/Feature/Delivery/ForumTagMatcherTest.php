<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Digest\Discord\DiscordException;
use App\Digest\Discord\ForumTagMatcher;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ForumTagMatcherTest extends TestCase
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

    public function test_matches_display_names_in_each_forum_and_preserves_slug_order(): void
    {
        Http::fake([
            self::API.'/channels/forum-1' => Http::response(['available_tags' => [['id' => '11', 'name' => 'AI'], ['id' => '12', 'name' => 'Laravel']]]),
            self::API.'/channels/forum-2' => Http::response(['available_tags' => [['id' => '21', 'name' => 'AI']]]),
        ]);
        $matcher = app(ForumTagMatcher::class);

        $this->assertSame(['12', '11'], $matcher->tagIds('forum-1', ['laravel', 'unknown', 'ai']));
        $this->assertSame(['21'], $matcher->tagIds('forum-2', ['ai']));
        Http::assertNotSent(fn (Request $r) => $r->method() === 'PATCH');
        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && $r->url() === self::API.'/channels/forum-1'
            && $r->hasHeader('Authorization', 'Bot bot-token'));
    }

    public function test_creates_missing_tags_with_truncated_names_and_preserves_existing_tag_metadata(): void
    {
        $name = str_repeat('é', 21);
        Tag::query()->create(['slug' => 'long', 'name' => $name]);
        $existing = ['id' => '11', 'name' => 'AI', 'moderated' => true, 'emoji_id' => null, 'emoji_name' => '🤖'];
        $nextId = 12;
        Http::fake(function (Request $r) use ($existing, &$nextId) {
            if ($r->method() === 'GET') {
                return Http::response(['available_tags' => [$existing]]);
            }

            return Http::response(['available_tags' => array_map(function (array $tag) use (&$nextId) {
                return isset($tag['id']) ? $tag : ['id' => (string) $nextId++, 'name' => $tag['name']];
            }, $r['available_tags'])]);
        });

        $this->assertSame(['12', '11', '13'], app(ForumTagMatcher::class)->tagIds('forum-1', ['long', 'ai', 'laravel']));

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
            && $r->url() === self::API.'/channels/forum-1'
            && $r->hasHeader('Authorization', 'Bot bot-token')
            && $r['available_tags'] === [$existing, ['name' => mb_substr($name, 0, 20)], ['name' => 'Laravel']]);
        Http::assertSentCount(2);
    }

    public function test_matches_an_existing_truncated_display_name_without_creating_it_again(): void
    {
        Tag::query()->create(['slug' => 'long', 'name' => str_repeat('é', 21)]);
        Http::fake([self::API.'/channels/forum-1' => Http::response(['available_tags' => [['id' => '11', 'name' => str_repeat('é', 20)]]])]);

        $this->assertSame(['11'], app(ForumTagMatcher::class)->tagIds('forum-1', ['long']));

        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $r) => $r->method() === 'PATCH');
    }

    public function test_creates_only_as_many_tags_as_the_forum_has_room_for(): void
    {
        $existing = array_map(fn (int $i) => ['id' => (string) $i, 'name' => "Tag {$i}"], range(1, 19));
        Http::fake([
            self::API.'/channels/forum-1' => Http::sequence()
                ->push(['available_tags' => $existing])
                ->push(['available_tags' => [...$existing, ['id' => '20', 'name' => 'AI']]]),
        ]);

        $this->assertSame(['20'], app(ForumTagMatcher::class)->tagIds('forum-1', ['ai', 'laravel']));

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r['available_tags'] === [...$existing, ['name' => 'AI']]);
        Http::assertSentCount(2);
    }

    public function test_a_full_forum_keeps_matching_existing_tags_without_creating_missing_ones(): void
    {
        $tags = [['id' => '11', 'name' => 'AI'], ...array_map(fn (int $i) => ['id' => (string) (100 + $i), 'name' => "Tag {$i}"], range(1, 19))];
        Http::fake([self::API.'/channels/forum-1' => Http::response(['available_tags' => $tags])]);

        $this->assertSame(['11'], app(ForumTagMatcher::class)->tagIds('forum-1', ['laravel', 'ai']));

        Http::assertNotSent(fn (Request $r) => $r->method() === 'PATCH');
    }

    public function test_returns_at_most_five_tag_ids_in_requested_order(): void
    {
        $tags = [];
        $slugs = [];
        foreach (range(1, 6) as $i) {
            Tag::query()->create(['slug' => "tag-{$i}", 'name' => "Tag {$i}"]);
            $tags[] = ['id' => (string) $i, 'name' => "Tag {$i}"];
            $slugs[] = "tag-{$i}";
        }
        Http::fake([self::API.'/channels/forum-1' => Http::response(['available_tags' => $tags])]);

        $this->assertSame(['6', '5', '4', '3', '2'], app(ForumTagMatcher::class)->tagIds('forum-1', array_reverse($slugs)));
    }

    public function test_unknown_slugs_and_empty_input_return_no_tags_and_do_not_create_any(): void
    {
        Http::fake([self::API.'/channels/forum-1' => Http::response(['available_tags' => []])]);
        $matcher = app(ForumTagMatcher::class);

        $this->assertSame([], $matcher->tagIds('forum-1', ['unknown']));
        $this->assertSame([], $matcher->tagIds('forum-1', []));

        Http::assertNotSent(fn (Request $r) => $r->method() === 'PATCH');
    }

    public function test_tag_creation_failures_propagate(): void
    {
        Http::fake([self::API.'/channels/forum-1' => Http::sequence()
            ->push(['available_tags' => []])
            ->push(['message' => 'Missing Permissions'], 403)]);

        $this->expectException(DiscordException::class);
        $this->expectExceptionMessage('Missing Permissions');

        app(ForumTagMatcher::class)->tagIds('forum-1', ['ai']);
    }
}
