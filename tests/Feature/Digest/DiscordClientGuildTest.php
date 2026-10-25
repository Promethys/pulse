<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Discord\DiscordClient;
use App\Digest\Discord\DiscordException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DiscordClientGuildTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.discord.token' => 'bot-token']);
    }

    /** @param array<string, mixed> $json */
    #[DataProvider('endpoints')]
    public function test_returns_the_json_from_a_bot_authenticated_get(string $method, string $path, array $json): void
    {
        $url = 'https://discord.com/api/v10/'.$path;
        Http::fake([$url => Http::response($json)]);

        $this->assertSame($json, app(DiscordClient::class)->{$method}('456'));

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === $url
            && $request->hasHeader('Authorization', 'Bot bot-token'));
        Http::assertSentCount(1);
    }

    /** @param array<string, mixed> $json */
    #[DataProvider('endpoints')]
    public function test_an_http_error_raises_a_discord_exception(string $method, string $path, array $json): void
    {
        Http::fake(['https://discord.com/api/v10/'.$path => Http::response(['message' => 'Missing Access'], 403)]);

        $this->expectException(DiscordException::class);
        $this->expectExceptionMessage('Missing Access');

        app(DiscordClient::class)->{$method}('456');
    }

    /** @return array<string, array{string, string, array<string, mixed>}> */
    public static function endpoints(): array
    {
        return [
            'channel' => ['channel', 'channels/456', ['id' => '456', 'guild_id' => '789', 'name' => 'News', 'type' => 15]],
            'guild' => ['guild', 'guilds/456', ['id' => '456', 'name' => 'Test guild', 'icon' => null]],
        ];
    }
}
