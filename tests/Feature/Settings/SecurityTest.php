<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_is_displayed()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);
        Features::passkeys([
            'confirmPassword' => true,
        ]);

        $user = User::factory()->onboarded()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->where('canManagePasskeys', true)
                ->where('passkeys', [])
                ->where('canManageTwoFactor', true)
                ->where('twoFactorEnabled', false),
            );
    }

    public function test_security_page_requires_password_confirmation_when_enabled()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->onboarded()->create();

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('security.edit'));

        $response->assertRedirect(route('password.confirm'));
    }

    public function test_security_page_renders_without_two_factor_when_feature_is_disabled()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        config(['fortify.features' => []]);

        $user = User::factory()->onboarded()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->where('canManagePasskeys', false)
                ->where('passkeys', [])
                ->where('canManageTwoFactor', false)
                ->missing('twoFactorEnabled')
                ->missing('requiresConfirmation'),
            );
    }

    public function test_password_can_be_updated()
    {
        $user = User::factory()->onboarded()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $user = User::factory()->onboarded()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('security.edit'));
    }

    #[DataProvider('passwordPolicies')]
    public function test_password_hint_matches_the_active_policy(string $environment, string $hint, string $rules): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->onboarded()->create())
            ->withSession(['auth.password_confirmed_at' => time()]);
        $this->app->instance('env', $environment);

        $this->get(route('security.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security', false)
                ->where('passwordHint', $hint)
                ->where('passwordRules', $rules));
    }

    public function test_passwordless_users_can_manage_security_without_password_confirmation(): void
    {
        $this->withoutVite();
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);
        Features::passkeys(['confirmPassword' => true]);
        $user = User::factory()->onboarded()->create(['password' => null]);
        $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'provider-123']);

        $this->actingAs($user)->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Security')
                ->where('auth.user.has_password', false));
    }

    #[DataProvider('passwordStates')]
    public function test_inertia_shares_has_password_without_exposing_the_password(?string $password, bool $expected): void
    {
        $this->withoutVite();
        $user = User::factory()->onboarded()->create(['password' => $password]);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.has_password', $expected)
                ->missing('auth.user.password'));
    }

    public static function passwordStates(): array
    {
        return ['passwordless' => [null, false], 'password' => ['known-password', true]];
    }

    public function test_passwordless_users_can_set_a_password_and_keep_social_sign_in(): void
    {
        $user = User::factory()->onboarded()->create(['password' => null]);
        $account = $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'provider-123']);

        $this->actingAs($user)->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'password' => 'New-Password123!',
                'password_confirmation' => 'New-Password123!',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->assertTrue(Hash::check('New-Password123!', $user->refresh()->password));
        $this->assertTrue($user->has_password);
        $this->assertDatabaseHas('social_accounts', ['id' => $account->id, 'user_id' => $user->id]);

        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'New-Password123!'])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('invalidNewPasswords')]
    public function test_setting_a_first_password_still_validates_strength_and_confirmation(array $input): void
    {
        $user = User::factory()->onboarded()->create(['password' => null]);

        $this->actingAs($user)->from(route('security.edit'))
            ->put(route('user-password.update'), $input)
            ->assertRedirect(route('security.edit'))
            ->assertSessionHasErrors('password')
            ->assertSessionDoesntHaveErrors('current_password');

        $this->assertNull($user->fresh()->password);
    }

    public static function invalidNewPasswords(): array
    {
        return [
            'missing' => [[]],
            'weak' => [['password' => 'weak', 'password_confirmation' => 'weak']],
            'mismatch' => [['password' => 'New-Password123!', 'password_confirmation' => 'Different-Password123!']],
            'missing confirmation' => [['password' => 'New-Password123!']],
        ];
    }

    #[DataProvider('enabledSocialProviders')]
    public function test_security_lists_only_enabled_connected_providers_belonging_to_the_reader(array $enabled): void
    {
        $this->withoutVite();
        $user = User::factory()->onboarded()->create();
        $other = User::factory()->create();
        foreach (['google', 'github'] as $provider) {
            config(["services.{$provider}" => [
                'client_id' => 'test-client',
                'client_secret' => in_array($provider, $enabled, true) ? 'test-secret' : '',
                'redirect' => "https://example.com/auth/{$provider}/callback",
            ]]);
            $user->socialAccounts()->create(['provider' => $provider, 'provider_id' => 'reader-'.$provider]);
            $other->socialAccounts()->create(['provider' => $provider, 'provider_id' => 'other-'.$provider]);
        }

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('socialProviders', $enabled)
                ->has('connectedProviders', count($enabled))
                ->where('connectedProviders', fn ($accounts) => collect($accounts)->pluck('provider')->sort()->values()->all() === collect($enabled)->sort()->values()->all()
                    && collect($accounts)->every(fn ($account) => $user->socialAccounts()->whereKey($account['id'])->exists())));
    }

    public static function enabledSocialProviders(): array
    {
        return ['none' => [[]], 'google' => [['google']], 'github' => [['github']], 'both' => [['google', 'github']]];
    }

    public function test_passwordless_readers_can_enable_two_factor_without_password_confirmation(): void
    {
        $user = User::factory()->onboarded()->create(['password' => null]);

        $this->actingAs($user)->from(route('security.edit'))
            ->post(route('two-factor.enable'))
            ->assertRedirect(route('security.edit'))
            ->assertSessionHas('status', 'two-factor-authentication-enabled');

        $this->assertNotNull($user->fresh()->two_factor_secret);

        $this->get(route('two-factor.qr-code'))->assertOk()->assertJsonStructure(['svg', 'url']);
    }

    public function test_readers_with_a_password_must_confirm_it_before_enabling_two_factor(): void
    {
        $user = User::factory()->onboarded()->create();

        $this->actingAs($user)->post(route('two-factor.enable'))
            ->assertRedirect(route('password.confirm'));

        $this->assertNull($user->fresh()->two_factor_secret);

        $this->get(route('two-factor.qr-code'))->assertRedirect(route('password.confirm'));
    }

    public function test_passwordless_readers_can_manage_passkeys_without_password_confirmation(): void
    {
        $user = User::factory()->onboarded()->create(['password' => null]);
        $passkey = $this->passkey($user);

        $this->actingAs($user)->get(route('passkey.registration-options'))
            ->assertOk()
            ->assertJsonStructure(['options']);

        $this->delete(route('passkey.destroy', $passkey))->assertSessionHas('status', 'passkey-deleted');

        $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
    }

    public function test_readers_with_a_password_must_confirm_it_before_managing_passkeys(): void
    {
        $user = User::factory()->onboarded()->create();
        $passkey = $this->passkey($user);

        $this->actingAs($user)->get(route('passkey.registration-options'))
            ->assertRedirect(route('password.confirm'));

        $this->delete(route('passkey.destroy', $passkey))->assertRedirect(route('password.confirm'));

        $this->assertDatabaseHas('passkeys', ['id' => $passkey->id]);
        $this->assertNull(session('passkey.registration_options'));
    }

    public function test_readers_with_a_recent_password_confirmation_can_manage_passkeys(): void
    {
        $user = User::factory()->onboarded()->create();

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('passkey.registration-options'))
            ->assertOk()
            ->assertJsonStructure(['options']);
    }

    public static function passwordPolicies(): array
    {
        return [
            'production' => [
                'production',
                'Use at least 12 characters, including uppercase and lowercase letters, a number and a symbol. Choose a password that has not appeared in a data breach.',
                'minlength: 12; required: lower; required: upper; required: digit; required: special;',
            ],
            'testing' => ['testing', 'Use at least 8 characters.', 'minlength: 8;'],
            'local' => ['local', 'Use at least 8 characters.', 'minlength: 8;'],
        ];
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
