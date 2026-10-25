<?php

declare(strict_types=1);

namespace Tests\Feature\Discord;

use App\Digest\Discord\DiscordException;
use App\Digest\Discord\DiscordInstall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscordInstallTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_URL = 'https://discord.com/api/v10/oauth2/token';

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

    public function test_authorize_url_contains_the_bot_install_parameters_and_absolute_callback(): void
    {
        $state = 'state with + & ? characters';
        $url = app(DiscordInstall::class)->authorizeUrl($state);
        $parts = parse_url($url);
        parse_str($parts['query'], $query);

        $this->assertSame('https', $parts['scheme']);
        $this->assertSame('discord.com', $parts['host']);
        $this->assertSame('/oauth2/authorize', $parts['path']);
        $this->assertSame('274877975568', DiscordInstall::PERMISSIONS);
        $this->assertEquals([
            'client_id' => '123',
            'scope' => 'bot',
            'permissions' => '274877975568',
            'response_type' => 'code',
            'redirect_uri' => route('discord.callback', [], true),
            'state' => $state,
        ], $query);
        $this->assertNotNull(parse_url($query['redirect_uri'], PHP_URL_HOST));
        Http::assertNothingSent();
    }

    public function test_exchange_posts_form_fields_and_returns_only_the_guild_id_and_name(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response([
                'access_token' => 'access-token',
                'guild' => ['id' => '456', 'name' => 'Developers', 'owner' => true],
            ]),
        ]);

        $guild = app(DiscordInstall::class)->exchange('code with + & characters');

        $this->assertSame(['id' => '456', 'name' => 'Developers'], $guild);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::TOKEN_URL
            && $r->isForm()
            && $r->data() == [
                'grant_type' => 'authorization_code',
                'code' => 'code with + & characters',
                'redirect_uri' => route('discord.callback', [], true),
                'client_id' => '123',
                'client_secret' => 'secret',
            ]);
        Http::assertSentCount(1);
    }

    public function test_exchange_throws_on_an_http_error(): void
    {
        Http::fake([self::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->expectException(DiscordException::class);

        app(DiscordInstall::class)->exchange('expired-code');
    }

    public function test_exchange_throws_when_the_response_has_no_guild(): void
    {
        Http::fake([self::TOKEN_URL => Http::response(['access_token' => 'access-token'])]);

        $this->expectException(DiscordException::class);

        app(DiscordInstall::class)->exchange('code');
    }

    public function test_exchange_throws_when_the_guild_has_no_id(): void
    {
        Http::fake([self::TOKEN_URL => Http::response(['guild' => ['name' => 'Developers']])]);

        $this->expectException(DiscordException::class);

        app(DiscordInstall::class)->exchange('code');
    }

    public function test_exchange_throws_when_the_guild_id_is_empty(): void
    {
        Http::fake([self::TOKEN_URL => Http::response(['guild' => ['id' => '', 'name' => 'Developers']])]);

        $this->expectException(DiscordException::class);

        app(DiscordInstall::class)->exchange('code');
    }
}
