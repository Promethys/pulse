<?php

declare(strict_types=1);

namespace Tests\Feature\Discord;

use App\Digest\Discord\DiscordClient;
use App\Digest\Discord\DiscordException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ForumChannelsTest extends TestCase
{
    use RefreshDatabase;

    private const CHANNELS_URL = 'https://discord.com/api/v10/guilds/456/channels';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'services.discord.client_id' => '123',
            'services.discord.client_secret' => 'secret',
            'services.discord.token' => 'bot-token',
        ]);
    }

    public function test_returns_only_forums_sorted_by_position_with_only_id_and_name(): void
    {
        Http::fake([
            self::CHANNELS_URL => Http::response([
                ['id' => 'late', 'name' => 'Learning', 'type' => 15, 'position' => 10, 'topic' => 'Tips'],
                ['id' => 'text', 'name' => 'Chat', 'type' => 0, 'position' => 0],
                ['id' => 'first', 'name' => 'News', 'type' => 15, 'position' => 2, 'guild_id' => '456'],
                ['id' => 'string', 'name' => 'String type', 'type' => '15', 'position' => 1],
                ['id' => 'voice', 'name' => 'Voice', 'type' => 2, 'position' => 3],
                ['id' => 'media', 'name' => 'Media', 'type' => 16, 'position' => 4],
            ]),
        ]);

        $channels = app(DiscordClient::class)->forumChannels('456');

        $this->assertSame([
            ['id' => 'first', 'name' => 'News'],
            ['id' => 'late', 'name' => 'Learning'],
        ], $channels);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && $r->url() === self::CHANNELS_URL
            && $r->hasHeader('Authorization', 'Bot bot-token'));
        Http::assertSentCount(1);
    }

    public function test_returns_an_empty_list_when_the_guild_has_no_forums(): void
    {
        Http::fake([
            self::CHANNELS_URL => Http::response([
                ['id' => 'text', 'name' => 'Chat', 'type' => 0, 'position' => 0],
            ]),
        ]);

        $this->assertSame([], app(DiscordClient::class)->forumChannels('456'));
    }

    public function test_throws_on_an_http_error(): void
    {
        Http::fake([self::CHANNELS_URL => Http::response(['message' => 'Missing Access'], 403)]);

        $this->expectException(DiscordException::class);

        app(DiscordClient::class)->forumChannels('456');
    }
}
