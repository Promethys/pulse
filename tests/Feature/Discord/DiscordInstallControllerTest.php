<?php

declare(strict_types=1);

namespace Tests\Feature\Discord;

use App\Digest\Discord\DiscordInstall;
use App\Models\DiscordGuild;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscordInstallControllerTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_URL = 'https://discord.com/api/v10/oauth2/token';

    private const STATE = '0123456789012345678901234567890123456789';

    private const ERROR_TOAST = ['type' => 'error', 'message' => 'Pulse was not added to Discord.'];

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

    public function test_install_stores_a_fresh_random_state_and_redirects_to_discord(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('discord.install'));

        $state = session('discord_install_state');
        $this->assertIsString($state);
        $this->assertSame(40, strlen($state));
        $response->assertStatus(302)->assertRedirect(app(DiscordInstall::class)->authorizeUrl($state));
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame($state, $query['state']);

        $response = $this->get(route('discord.install'));

        $nextState = session('discord_install_state');
        $this->assertIsString($nextState);
        $this->assertSame(40, strlen($nextState));
        $this->assertNotSame($state, $nextState);
        $response->assertStatus(302)->assertRedirect(app(DiscordInstall::class)->authorizeUrl($nextState));
        Http::assertNothingSent();
    }

    public function test_valid_callback_stores_the_guild_for_only_the_logged_in_user_and_flashes_success(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Http::fake([self::TOKEN_URL => Http::response(['guild' => ['id' => '456', 'name' => 'Developers']])]);

        $response = $this->actingAs($user)
            ->withSession(['discord_install_state' => self::STATE])
            ->get(route('discord.callback', ['state' => self::STATE, 'code' => 'code']));

        $response->assertRedirect('/settings/delivery')
            ->assertSessionMissing('discord_install_state')
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Pulse was added to Developers.']);
        $this->assertDatabaseHas('discord_guilds', ['user_id' => $user->id, 'guild_id' => '456', 'name' => 'Developers']);
        $this->assertDatabaseMissing('discord_guilds', ['user_id' => $other->id]);
        $this->assertDatabaseCount('discord_guilds', 1);
        Http::assertSentCount(1);
    }

    public function test_wrong_state_is_forbidden_and_makes_no_http_call(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['discord_install_state' => self::STATE])
            ->get(route('discord.callback', ['state' => 'wrong-state', 'code' => 'code']))
            ->assertForbidden();

        $this->assertDatabaseCount('discord_guilds', 0);
        Http::assertNothingSent();
    }

    public function test_missing_query_state_is_forbidden_and_makes_no_http_call(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['discord_install_state' => self::STATE])
            ->get(route('discord.callback', ['code' => 'code']))
            ->assertForbidden();

        $this->assertDatabaseCount('discord_guilds', 0);
        Http::assertNothingSent();
    }

    public function test_missing_session_state_is_forbidden_and_makes_no_http_call(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('discord.callback', ['state' => self::STATE, 'code' => 'code']))
            ->assertForbidden();

        $this->assertDatabaseCount('discord_guilds', 0);
        Http::assertNothingSent();
    }

    public function test_missing_state_in_both_query_and_session_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('discord.callback', ['code' => 'code']))
            ->assertForbidden();

        $this->assertDatabaseCount('discord_guilds', 0);
        Http::assertNothingSent();
    }

    public function test_state_can_only_be_used_once(): void
    {
        Http::fake([self::TOKEN_URL => Http::response(['guild' => ['id' => '456', 'name' => 'Developers']])]);
        $this->actingAs(User::factory()->create())
            ->withSession(['discord_install_state' => self::STATE]);
        $url = route('discord.callback', ['state' => self::STATE, 'code' => 'code']);

        $this->get($url)->assertRedirect('/settings/delivery')->assertSessionMissing('discord_install_state');
        $this->get($url)->assertForbidden();

        $this->assertDatabaseCount('discord_guilds', 1);
        Http::assertSentCount(1);
    }

    public function test_cancelled_install_flashes_an_error_consumes_state_and_stores_nothing(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['discord_install_state' => self::STATE])
            ->get(route('discord.callback', ['state' => self::STATE, 'error' => 'access_denied']))
            ->assertRedirect('/settings/delivery')
            ->assertSessionMissing('discord_install_state')
            ->assertInertiaFlash('toast', self::ERROR_TOAST);

        $this->assertDatabaseCount('discord_guilds', 0);
        Http::assertNothingSent();
    }

    public function test_cancelled_install_still_requires_a_valid_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['discord_install_state' => self::STATE])
            ->get(route('discord.callback', ['state' => 'wrong-state', 'error' => 'access_denied']))
            ->assertForbidden();

        $this->assertDatabaseCount('discord_guilds', 0);
        Http::assertNothingSent();
    }

    public function test_forged_guild_id_is_ignored_in_favour_of_the_token_response(): void
    {
        $user = User::factory()->create();
        Http::fake([self::TOKEN_URL => Http::response(['guild' => ['id' => '456', 'name' => 'Developers']])]);

        $this->actingAs($user)
            ->withSession(['discord_install_state' => self::STATE])
            ->get(route('discord.callback', ['state' => self::STATE, 'code' => 'code', 'guild_id' => 'forged']))
            ->assertRedirect('/settings/delivery');

        $this->assertDatabaseHas('discord_guilds', ['user_id' => $user->id, 'guild_id' => '456', 'name' => 'Developers']);
        $this->assertDatabaseMissing('discord_guilds', ['guild_id' => 'forged']);
        $this->assertDatabaseCount('discord_guilds', 1);
        Http::assertSentCount(1);
    }

    public function test_reinstall_updates_the_guild_name_and_keeps_one_row(): void
    {
        $user = User::factory()->create();
        $guild = DiscordGuild::factory()->create(['user_id' => $user->id, 'guild_id' => '456', 'name' => 'Old name']);
        Http::fake([self::TOKEN_URL => Http::response(['guild' => ['id' => '456', 'name' => 'New name']])]);

        $this->actingAs($user)
            ->withSession(['discord_install_state' => self::STATE])
            ->get(route('discord.callback', ['state' => self::STATE, 'code' => 'code']))
            ->assertRedirect('/settings/delivery')
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Pulse was added to New name.']);

        $this->assertSame('New name', $guild->refresh()->name);
        $this->assertDatabaseCount('discord_guilds', 1);
    }

    public function test_two_users_can_install_the_same_guild_without_updating_each_others_rows(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        Http::fake([
            self::TOKEN_URL => Http::sequence()
                ->push(['guild' => ['id' => '456', 'name' => 'Original name']])
                ->push(['guild' => ['id' => '456', 'name' => 'Renamed guild']]),
        ]);

        $this->actingAs($first)
            ->withSession(['discord_install_state' => self::STATE])
            ->get(route('discord.callback', ['state' => self::STATE, 'code' => 'first-code']))
            ->assertRedirect('/settings/delivery');
        $this->actingAs($second)
            ->withSession(['discord_install_state' => self::STATE])
            ->get(route('discord.callback', ['state' => self::STATE, 'code' => 'second-code']))
            ->assertRedirect('/settings/delivery');

        $this->assertDatabaseHas('discord_guilds', ['user_id' => $first->id, 'guild_id' => '456', 'name' => 'Original name']);
        $this->assertDatabaseHas('discord_guilds', ['user_id' => $second->id, 'guild_id' => '456', 'name' => 'Renamed guild']);
        $this->assertDatabaseCount('discord_guilds', 2);
        Http::assertSentCount(2);
    }

    public function test_token_endpoint_failure_flashes_an_error_consumes_state_and_stores_nothing(): void
    {
        Http::fake([self::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->actingAs(User::factory()->create())
            ->withSession(['discord_install_state' => self::STATE])
            ->get(route('discord.callback', ['state' => self::STATE, 'code' => 'expired-code']))
            ->assertRedirect('/settings/delivery')
            ->assertSessionMissing('discord_install_state')
            ->assertInertiaFlash('toast', self::ERROR_TOAST);

        $this->assertDatabaseCount('discord_guilds', 0);
        Http::assertSentCount(1);
    }

    public function test_token_response_without_a_guild_flashes_an_error_and_ignores_query_guild_id(): void
    {
        Http::fake([self::TOKEN_URL => Http::response(['access_token' => 'access-token'])]);

        $this->actingAs(User::factory()->create())
            ->withSession(['discord_install_state' => self::STATE])
            ->get(route('discord.callback', ['state' => self::STATE, 'code' => 'code', 'guild_id' => 'forged']))
            ->assertRedirect('/settings/delivery')
            ->assertSessionMissing('discord_install_state')
            ->assertInertiaFlash('toast', self::ERROR_TOAST);

        $this->assertDatabaseCount('discord_guilds', 0);
        Http::assertSentCount(1);
    }

    public function test_guests_cannot_install(): void
    {
        $this->get(route('discord.install'))->assertRedirect(route('login'))
            ->assertSessionMissing('discord_install_state');

        Http::assertNothingSent();
    }

    public function test_guests_cannot_use_the_callback(): void
    {
        $this->withSession(['discord_install_state' => self::STATE])
            ->get(route('discord.callback', ['state' => self::STATE, 'code' => 'code']))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('discord_guilds', 0);
        Http::assertNothingSent();
    }

    public function test_unverified_users_cannot_install(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('discord.install'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionMissing('discord_install_state');

        Http::assertNothingSent();
    }

    public function test_unverified_users_cannot_use_the_callback(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->withSession(['discord_install_state' => self::STATE])
            ->get(route('discord.callback', ['state' => self::STATE, 'code' => 'code']))
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('discord_guilds', 0);
        Http::assertNothingSent();
    }
}
