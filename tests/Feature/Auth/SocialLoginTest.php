<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\DeliveryChannelType;
use App\Enums\PostKind;
use App\Models\SocialAccount;
use App\Models\User;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as ProviderUser;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();
        Mail::fake();
        Notification::fake();

        foreach (['google', 'github'] as $provider) {
            config(["services.{$provider}" => [
                'client_id' => 'test-client',
                'client_secret' => 'test-secret',
                'redirect' => "https://example.com/auth/{$provider}/callback",
            ]]);
            Socialite::fake($provider, $this->providerUser());
        }

        config(['fortify.features' => [Features::registration(), Features::twoFactorAuthentication(['confirm' => true])]]);
    }

    public static function providers(): array
    {
        return ['google' => ['google'], 'github' => ['github']];
    }

    public static function endpoints(): array
    {
        return [
            'google redirect' => ['google', 'social.redirect'],
            'google callback' => ['google', 'social.callback'],
            'github redirect' => ['github', 'social.redirect'],
            'github callback' => ['github', 'social.callback'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_disabled_providers_return_404(string $provider, string $route): void
    {
        config(["services.{$provider}.client_secret" => '']);

        $this->get(route($route, $provider))->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('social_accounts', 0);
    }

    #[DataProvider('providers')]
    public function test_enabled_providers_redirect_to_socialite(string $provider): void
    {
        $this->get(route('social.redirect', $provider))
            ->assertRedirect("https://socialite.fake/{$provider}/authorize");

        $this->assertGuest();
    }

    public function test_github_redirect_requests_the_email_scope(): void
    {
        $this->get(route('social.redirect', 'github'))->assertRedirect();

        $this->assertContains('user:email', Socialite::driver('github')->getScopes());
    }

    #[DataProvider('endpoints')]
    public function test_authenticated_users_are_redirected_away(string $provider, string $route): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route($route, $provider))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_social_requests_are_limited_per_ip_across_providers_and_endpoints(): void
    {
        $this->freezeSecond();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->get(route('social.redirect', 'google'))->assertRedirect();
        }

        $this->get(route('social.redirect', 'github'))->assertTooManyRequests();
        $this->get(route('social.callback', 'github'))->assertTooManyRequests();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.2'])
            ->get(route('social.redirect', 'github'))->assertRedirect();

        $this->travel(61)->seconds();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get(route('social.redirect', 'google'))->assertRedirect();
    }

    #[DataProvider('providers')]
    public function test_existing_links_sign_in_the_linked_user_without_requiring_email(string $provider): void
    {
        $user = User::factory()->create();
        $this->link($user, $provider);
        Socialite::fake($provider, $this->providerUser(['email' => null, 'email_verified' => false]));
        config(['fortify.features' => []]);

        $this->withSession(['url.intended' => '/settings/profile']);
        $sessionId = session()->getId();

        $this->get(route('social.callback', $provider))
            ->assertRedirect('/settings/profile')
            ->assertCookie(auth()->guard()->getRecallerName());

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('social_accounts', 1);
    }

    #[DataProvider('emailMatches')]
    public function test_verified_email_links_an_existing_user_even_when_registration_is_closed(string $provider, string $email, bool $verified): void
    {
        $this->freezeSecond();
        $verifiedAt = $verified ? now()->subDay() : null;
        $user = User::factory()->create(['email' => 'reader@example.com', 'email_verified_at' => $verifiedAt]);
        Socialite::fake($provider, $this->providerUser(['email' => $email]));
        config(['fortify.features' => []]);

        $this->get(route('social.callback', $provider))->assertRedirect(config('fortify.home'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('social_accounts', 1);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => $provider, 'provider_id' => 'provider-123']);
        $this->assertTrue($user->fresh()->email_verified_at->equalTo($verifiedAt ?? now()));
    }

    public function test_linking_an_unverified_account_revokes_the_password_and_sessions_of_whoever_registered_it(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->unverified()->create(['email' => 'reader@example.com', 'password' => 'known-password', 'remember_token' => 'old-token']);
        DB::table('sessions')->insert(['id' => 'squatter-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        Socialite::fake('google', $this->providerUser(['email' => 'reader@example.com']));

        $this->get(route('social.callback', 'google'))->assertRedirect(config('fortify.home'));

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->password);
        $this->assertNotSame('old-token', $user->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'squatter-session']);
    }

    public function test_linking_a_verified_account_keeps_its_password(): void
    {
        $user = User::factory()->create(['email' => 'reader@example.com', 'password' => 'known-password']);
        Socialite::fake('google', $this->providerUser(['email' => 'reader@example.com']));

        $this->get(route('social.callback', 'google'));

        $this->assertTrue(Hash::check('known-password', $user->fresh()->password));
    }

    public static function emailMatches(): array
    {
        return [
            'google same email' => ['google', 'reader@example.com', false],
            'google mixed case' => ['google', 'Reader@Example.COM', false],
            'google already verified' => ['google', 'reader@example.com', true],
            'github same email' => ['github', 'reader@example.com', false],
            'github mixed case' => ['github', 'Reader@Example.COM', false],
            'github already verified' => ['github', 'reader@example.com', true],
        ];
    }

    #[DataProvider('newUsers')]
    public function test_new_users_receive_verified_accounts_and_default_digest_delivery(string $provider, ?string $name, ?string $nickname, string $expectedName): void
    {
        $this->freezeSecond();
        Socialite::fake($provider, $this->providerUser(['name' => $name, 'nickname' => $nickname]));

        $this->get(route('social.callback', $provider))
            ->assertRedirect(config('fortify.home'))
            ->assertCookie(auth()->guard()->getRecallerName());

        $user = User::query()->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame($expectedName, $user->name);
        $this->assertSame('reader@example.com', $user->email);
        $this->assertTrue($user->email_verified_at->equalTo(now()));
        $this->assertNull($user->password);
        $this->assertDatabaseHas('digest_settings', ['user_id' => $user->id]);
        $channel = $user->deliveryChannels()->sole();
        $this->assertSame('In the app', $channel->name);
        $this->assertSame(DeliveryChannelType::InApp, $channel->type);
        $this->assertSame([PostKind::News->value, PostKind::Learning->value], $channel->kinds);
        $this->assertTrue($channel->enabled);
        $this->assertNull($channel->settings);
        $account = $user->socialAccounts()->sole();
        $this->assertSame($user->id, $account->user->id);
        $this->assertSame($provider, $account->provider);
        $this->assertSame('provider-123', $account->provider_id);
        $this->assertArrayNotHasKey('token', $account->getAttributes());
        $this->assertArrayNotHasKey('refresh_token', $account->getAttributes());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
    }

    public static function newUsers(): array
    {
        return [
            'google name' => ['google', 'Reader Name', 'nickname', 'Reader Name'],
            'google nickname' => ['google', null, 'nickname', 'nickname'],
            'google local part' => ['google', null, null, 'reader'],
            'github name' => ['github', 'Reader Name', 'nickname', 'Reader Name'],
            'github nickname' => ['github', '', 'nickname', 'nickname'],
            'github local part' => ['github', '', '', 'reader'],
        ];
    }

    #[DataProvider('providers')]
    public function test_registration_closed_rejects_unknown_users(string $provider): void
    {
        config(['fortify.features' => []]);

        $this->get(route('social.callback', $provider))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Registration is closed on this instance.']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('social_accounts', 0);
        $this->assertDatabaseCount('digest_settings', 0);
        $this->assertDatabaseCount('delivery_channels', 0);
    }

    #[DataProvider('unverifiedEmails')]
    public function test_unverified_or_missing_email_cannot_create_or_link_accounts(string $provider, ?string $email, mixed $verified): void
    {
        $user = User::factory()->unverified()->create(['email' => 'reader@example.com']);
        Socialite::fake($provider, $this->providerUser(['email' => $email, 'email_verified' => $verified]));

        $this->get(route('social.callback', $provider))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('social_accounts', 0);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public static function unverifiedEmails(): array
    {
        return [
            'google false' => ['google', 'reader@example.com', false],
            'google absent flag' => ['google', 'reader@example.com', null],
            'google string true' => ['google', 'reader@example.com', 'true'],
            'google integer true' => ['google', 'reader@example.com', 1],
            'google missing email' => ['google', null, true],
            'github missing email' => ['github', null, null],
            'github empty email' => ['github', '', null],
        ];
    }

    #[DataProvider('socialiteErrors')]
    public function test_socialite_errors_return_a_useful_login_error(string $provider, string $error): void
    {
        Socialite::fake($provider, function () use ($error): never {
            if ($error === ServerException::class) {
                throw new ServerException('Provider failure', new Request('GET', 'https://example.com/user'), new Response(500));
            }

            throw new $error('Provider failure');
        });

        $this->get(route('social.callback', $provider))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Sign-in with '.($provider === 'google' ? 'Google' : 'GitHub')." didn't work. Try again."]);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public static function socialiteErrors(): array
    {
        return [
            'google invalid state' => ['google', InvalidStateException::class],
            'github invalid state' => ['github', InvalidStateException::class],
            'google denied consent' => ['google', RuntimeException::class],
            'github denied consent' => ['github', RuntimeException::class],
            'google HTTP failure' => ['google', ServerException::class],
            'github HTTP failure' => ['github', ServerException::class],
        ];
    }

    #[DataProvider('twoFactorUsers')]
    public function test_confirmed_two_factor_users_must_complete_the_challenge(string $provider, bool $linked): void
    {
        $user = User::factory()->withTwoFactor()->create(['email' => 'reader@example.com']);
        if ($linked) {
            $this->link($user, $provider);
        }

        $this->get(route('social.callback', $provider))
            ->assertRedirect(route('two-factor.login'))
            ->assertSessionHas('login.id', $user->id)
            ->assertSessionHas('login.remember', true)
            ->assertCookieMissing(auth()->guard()->getRecallerName());

        $this->assertGuest();
    }

    public static function twoFactorUsers(): array
    {
        return [
            'google linked' => ['google', true],
            'google email match' => ['google', false],
            'github linked' => ['github', true],
            'github email match' => ['github', false],
        ];
    }

    #[DataProvider('providers')]
    public function test_unconfirmed_two_factor_setup_does_not_require_a_challenge(string $provider): void
    {
        $user = User::factory()->withTwoFactor()->create(['two_factor_confirmed_at' => null]);
        $this->link($user, $provider);

        $this->get(route('social.callback', $provider))
            ->assertRedirect(config('fortify.home'))
            ->assertSessionMissing('login.id');

        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('sharedProviders')]
    public function test_inertia_shares_only_enabled_providers(array $enabled): void
    {
        foreach (['google', 'github'] as $provider) {
            if (! in_array($provider, $enabled, true)) {
                config(["services.{$provider}.redirect" => null]);
            }
        }

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('socialProviders', $enabled));
    }

    public static function sharedProviders(): array
    {
        return ['none' => [[]], 'google only' => [['google']], 'github only' => [['github']], 'both' => [['google', 'github']]];
    }

    #[DataProvider('providers')]
    public function test_link_intent_connects_to_the_signed_in_user_instead_of_matching_email(string $provider): void
    {
        $user = User::factory()->onboarded()->create(['email' => 'owner@example.com', 'name' => 'Original Name']);
        $other = User::factory()->create(['email' => 'reader@example.com']);
        $password = $user->password;
        config(['fortify.features' => []]);

        $this->actingAs($user)->withSession($this->recentConfirmation())
            ->get(route('social.redirect', ['provider' => $provider, 'intent' => 'link']))
            ->assertRedirect("https://socialite.fake/{$provider}/authorize");

        $this->get(route('social.callback', $provider))
            ->assertRedirect(route('security.edit'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => $provider, 'provider_id' => 'provider-123']);
        $this->assertSame(0, $other->socialAccounts()->count());
        $this->assertSame('owner@example.com', $user->fresh()->email);
        $this->assertSame('Original Name', $user->fresh()->name);
        $this->assertSame($password, $user->fresh()->password);
        $this->assertDatabaseCount('users', 2);
    }

    #[DataProvider('providers')]
    public function test_link_intent_refuses_an_identity_owned_by_another_user(string $provider): void
    {
        $user = User::factory()->onboarded()->create();
        $other = User::factory()->create();
        $account = $this->link($other, $provider);

        $this->actingAs($user)->withSession($this->recentConfirmation())->get(route('social.redirect', ['provider' => $provider, 'intent' => 'link']))
            ->assertRedirect("https://socialite.fake/{$provider}/authorize");

        $this->get(route('social.callback', $provider))
            ->assertRedirect(route('security.edit'))
            ->assertSessionHasErrors('social');

        $this->assertAuthenticatedAs($user);
        $this->assertSame($other->id, $account->fresh()->user_id);
        $this->assertSame(0, $user->socialAccounts()->count());
        $this->assertDatabaseCount('social_accounts', 1);
    }

    #[DataProvider('providers')]
    public function test_link_failure_returns_to_security_without_changing_accounts(string $provider): void
    {
        $user = User::factory()->onboarded()->create();
        Socialite::fake($provider, function (): never {
            throw new InvalidStateException;
        });

        $this->actingAs($user)->withSession($this->recentConfirmation())->get(route('social.redirect', ['provider' => $provider, 'intent' => 'link']))
            ->assertRedirect("https://socialite.fake/{$provider}/authorize");

        $this->get(route('social.callback', $provider))
            ->assertRedirect(route('security.edit'))
            ->assertSessionHasErrors('social');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('social_accounts', 0);
    }

    #[DataProvider('providers')]
    public function test_disabled_providers_cannot_be_connected_from_settings(string $provider): void
    {
        config(["services.{$provider}.client_secret" => '']);
        $user = User::factory()->onboarded()->create();

        $this->actingAs($user)->withSession($this->recentConfirmation())->get(route('social.redirect', ['provider' => $provider, 'intent' => 'link']))
            ->assertNotFound();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('social_accounts', 0);
    }

    #[DataProvider('providers')]
    public function test_a_password_allows_disconnecting_the_only_social_account(string $provider): void
    {
        $user = User::factory()->onboarded()->create();
        $account = $this->link($user, $provider);
        $other = User::factory()->create();
        $other->socialAccounts()->create(['provider' => $provider, 'provider_id' => 'other-identity']);

        $this->actingAs($user)->withSession($this->recentConfirmation())->delete(route('social.unlink', $provider))
            ->assertRedirect(route('security.edit'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('social_accounts', ['id' => $account->id]);
        $this->assertSame(1, $other->socialAccounts()->count());
        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('providers')]
    public function test_a_second_social_account_allows_disconnecting_without_a_password(string $provider): void
    {
        $user = User::factory()->onboarded()->create(['password' => null]);
        $account = $this->link($user, $provider);
        $remaining = $this->link($user, $provider === 'google' ? 'github' : 'google');

        $this->actingAs($user)->delete(route('social.unlink', $provider))
            ->assertRedirect(route('security.edit'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('social_accounts', ['id' => $account->id]);
        $this->assertSame($remaining->id, $user->socialAccounts()->sole()->id);
        $this->assertNull($user->fresh()->password);
        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('providers')]
    public function test_the_last_sign_in_method_cannot_be_disconnected(string $provider): void
    {
        $user = User::factory()->onboarded()->create(['password' => null]);
        $account = $this->link($user, $provider);

        $this->actingAs($user)->delete(route('social.unlink', $provider))
            ->assertRedirect(route('security.edit'))
            ->assertSessionHasErrors('social');

        $this->assertDatabaseHas('social_accounts', ['id' => $account->id, 'user_id' => $user->id]);
        $this->assertNull($user->fresh()->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_guests_cannot_disconnect_an_account(): void
    {
        $user = User::factory()->create();
        $account = $this->link($user, 'google');

        $this->delete(route('social.unlink', 'google'))->assertRedirect(route('login'));

        $this->assertDatabaseHas('social_accounts', ['id' => $account->id]);
    }

    #[DataProvider('providers')]
    public function test_unverified_readers_cannot_start_linking_a_provider(string $provider): void
    {
        $user = User::factory()->onboarded()->unverified()->create(['password' => null]);

        $this->actingAs($user)->withSession($this->recentConfirmation())
            ->get(route('social.redirect', ['provider' => $provider, 'intent' => 'link']))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionMissing('social.link');

        $this->get(route('social.callback', $provider));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('social_accounts', 0);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_reclaiming_an_unverified_account_removes_every_sign_in_method_of_whoever_registered_it(): void
    {
        $user = User::factory()->unverified()->withTwoFactor()->create(['email' => 'reader@example.com', 'password' => 'known-password']);
        $user->socialAccounts()->create(['provider' => 'github', 'provider_id' => 'squatter-github']);
        $this->passkey($user);
        Socialite::fake('google', $this->providerUser(['email' => 'reader@example.com']));

        $this->get(route('social.callback', 'google'))
            ->assertRedirect(config('fortify.home'))
            ->assertSessionMissing('login.id');

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertNull($user->password);
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertSame(0, $user->passkeys()->count());
        $this->assertDatabaseCount('passkeys', 0);
        $account = $user->socialAccounts()->sole();
        $this->assertSame('google', $account->provider);
        $this->assertSame('provider-123', $account->provider_id);
        $this->assertDatabaseMissing('social_accounts', ['provider_id' => 'squatter-github']);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_reclaiming_an_unverified_account_leaves_other_readers_untouched(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'reader@example.com']);
        $other = User::factory()->withTwoFactor()->create();
        $otherAccount = $other->socialAccounts()->create(['provider' => 'github', 'provider_id' => 'other-github']);
        $this->passkey($other);
        Socialite::fake('google', $this->providerUser(['email' => 'reader@example.com']));

        $this->get(route('social.callback', 'google'))->assertRedirect(config('fortify.home'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_accounts', ['id' => $otherAccount->id, 'user_id' => $other->id]);
        $this->assertSame(1, $other->passkeys()->count());
        $this->assertNotNull($other->fresh()->two_factor_secret);
        $this->assertNotNull($other->fresh()->two_factor_confirmed_at);
    }

    public function test_linking_a_verified_account_keeps_its_social_accounts_passkeys_and_two_factor(): void
    {
        $user = User::factory()->withTwoFactor()->create(['email' => 'reader@example.com', 'password' => 'known-password']);
        $github = $user->socialAccounts()->create(['provider' => 'github', 'provider_id' => 'reader-github']);
        $passkey = $this->passkey($user);
        $secret = $user->two_factor_secret;
        $codes = $user->two_factor_recovery_codes;
        Socialite::fake('google', $this->providerUser(['email' => 'reader@example.com']));

        $this->get(route('social.callback', 'google'))
            ->assertRedirect(route('two-factor.login'))
            ->assertSessionHas('login.id', $user->id);

        $this->assertGuest();
        $user->refresh();
        $this->assertTrue(Hash::check('known-password', $user->password));
        $this->assertSame($secret, $user->two_factor_secret);
        $this->assertSame($codes, $user->two_factor_recovery_codes);
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertDatabaseHas('passkeys', ['id' => $passkey->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('social_accounts', ['id' => $github->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'provider-123']);
    }

    #[DataProvider('providers')]
    public function test_link_intent_requires_a_recent_password_confirmation(string $provider): void
    {
        $user = User::factory()->onboarded()->create();

        $this->actingAs($user)
            ->get(route('social.redirect', ['provider' => $provider, 'intent' => 'link']))
            ->assertRedirect(route('password.confirm'))
            ->assertSessionMissing('social.link');

        $this->get(route('social.callback', $provider));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('social_accounts', 0);
    }

    #[DataProvider('providers')]
    public function test_link_intent_proceeds_after_a_recent_password_confirmation(string $provider): void
    {
        $user = User::factory()->onboarded()->create();

        $this->actingAs($user)->withSession($this->recentConfirmation())
            ->get(route('social.redirect', ['provider' => $provider, 'intent' => 'link']))
            ->assertRedirect("https://socialite.fake/{$provider}/authorize");

        $this->get(route('social.callback', $provider))->assertRedirect(route('security.edit'));

        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => $provider]);
    }

    #[DataProvider('providers')]
    public function test_passwordless_readers_link_without_a_password_confirmation(string $provider): void
    {
        $user = User::factory()->onboarded()->create(['password' => null]);
        $this->link($user, $provider === 'google' ? 'github' : 'google');

        $this->actingAs($user)
            ->get(route('social.redirect', ['provider' => $provider, 'intent' => 'link']))
            ->assertRedirect("https://socialite.fake/{$provider}/authorize");

        $this->get(route('social.callback', $provider))
            ->assertRedirect(route('security.edit'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => $provider, 'provider_id' => 'provider-123']);
    }

    #[DataProvider('providers')]
    public function test_unlink_requires_a_recent_password_confirmation(string $provider): void
    {
        $user = User::factory()->onboarded()->create();
        $account = $this->link($user, $provider);

        $this->actingAs($user)->delete(route('social.unlink', $provider))
            ->assertRedirect(route('password.confirm'));

        $this->assertDatabaseHas('social_accounts', ['id' => $account->id, 'user_id' => $user->id]);
    }

    #[DataProvider('providers')]
    public function test_an_expired_password_confirmation_does_not_allow_unlinking(string $provider): void
    {
        $user = User::factory()->onboarded()->create();
        $account = $this->link($user, $provider);
        $expired = time() - (int) config('auth.password_timeout', 10800) - 1;

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => $expired])
            ->delete(route('social.unlink', $provider))
            ->assertRedirect(route('password.confirm'));

        $this->assertDatabaseHas('social_accounts', ['id' => $account->id]);
    }

    public function test_signed_in_social_requests_are_limited_per_reader_across_addresses(): void
    {
        $this->freezeSecond();
        $user = User::factory()->onboarded()->create(['password' => null]);
        $throttledAt = null;

        for ($attempt = 1; $attempt <= 60; $attempt++) {
            $request = $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$attempt}"]);
            $response = $attempt % 2 === 0
                ? $request->delete(route('social.unlink', 'github'))
                : $request->get(route('social.redirect', ['provider' => 'google', 'intent' => 'link']));

            if ($response->status() === 429) {
                $throttledAt = $attempt;
                break;
            }

            $response->assertRedirect();
        }

        $this->assertNotNull($throttledAt, 'Link and unlink requests from rotating addresses were never limited for one reader.');

        $other = User::factory()->onboarded()->create(['password' => null]);
        $this->actingAs($other)->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])
            ->delete(route('social.unlink', 'github'))
            ->assertRedirect(route('security.edit'));
    }

    private function providerUser(array $attributes = []): ProviderUser
    {
        return ProviderUser::fake(array_replace([
            'id' => 'provider-123',
            'name' => 'Reader Name',
            'nickname' => 'nickname',
            'email' => 'reader@example.com',
            'email_verified' => true,
        ], $attributes));
    }

    private function link(User $user, string $provider): SocialAccount
    {
        return $user->socialAccounts()->create(['provider' => $provider, 'provider_id' => 'provider-123']);
    }

    /**
     * @return array<string, int>
     */
    private function recentConfirmation(): array
    {
        return ['auth.password_confirmed_at' => time()];
    }

    private function passkey(User $user): Passkey
    {
        $credentialId = rtrim(strtr(base64_encode(hash('sha256', 'credential-'.$user->id, true)), '+/', '-_'), '=');

        return $user->passkeys()->create([
            'name' => 'Laptop',
            'credential_id' => $credentialId,
            'credential' => [
                'publicKeyCredentialId' => $credentialId,
                'type' => 'public-key',
                'transports' => [],
                'attestationType' => 'none',
                'counter' => 0,
            ],
        ]);
    }
}
