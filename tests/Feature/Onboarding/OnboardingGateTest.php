<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Digest\Discord\DiscordInstall;
use App\Models\DigestSettings;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OnboardingGateTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('gatedPages')]
    public function test_verified_users_are_gated_until_onboarded(string $path): void
    {
        $user = User::factory()->create();
        $settings = DigestSettings::factory()->for($user)->create();

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
            ->get($path)->assertRedirect('/onboarding');

        $settings->forceFill(['onboarded_at' => now()])->save();

        $this->actingAs($user->fresh())->get($path)->assertOk();
    }

    public static function gatedPages(): array
    {
        return array_map(fn (string $path) => [$path], [
            '/dashboard', '/settings', '/settings/profile', '/settings/appearance', '/settings/security',
        ]);
    }

    #[DataProvider('settingsEntryPoints')]
    public function test_missing_settings_are_created_with_defaults_before_checking_onboarding(string $path): void
    {
        $user = User::factory()->create();
        $this->assertDatabaseMissing('digest_settings', ['user_id' => $user->id]);

        $response = $this->actingAs($user)->get($path);

        if ($path === '/onboarding') {
            $response->assertOk();
        } else {
            $response->assertRedirect('/onboarding');
        }

        $settings = $user->digestSettings()->sole();
        $this->assertSame('UTC', $settings->timezone);
        $this->assertNull($settings->profile);
        $this->assertNull($settings->onboarded_at);
        $this->assertTrue($settings->news_enabled);
        $this->assertTrue($settings->learning_enabled);
        $this->assertSame('09:00', $settings->news_time);
        $this->assertSame('09:30', $settings->learning_time);
        $this->assertSame(20, $settings->max_items);

        $this->get($path);
        $this->assertSame(1, $user->digestSettings()->count());
    }

    public static function settingsEntryPoints(): array
    {
        return [['/dashboard'], ['/settings/profile'], ['/onboarding']];
    }

    public function test_onboarding_is_accessible_before_completion_and_redirects_to_dashboard_afterwards(): void
    {
        $user = User::factory()->create();
        $settings = DigestSettings::factory()->for($user)->create();

        $this->actingAs($user)->get('/onboarding')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Onboarding', false)
                ->where('settings.onboarded_at', null));

        $settings->forceFill(['onboarded_at' => now()])->save();

        $this->actingAs($user->fresh())->get('/onboarding')->assertRedirect('/dashboard');
    }

    public function test_a_gated_user_can_log_out(): void
    {
        $user = User::factory()->create();
        DigestSettings::factory()->for($user)->create();
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/onboarding');

        $this->post(route('logout'))->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_verification_routes_remain_accessible_before_onboarding(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        DigestSettings::factory()->for($user)->create();

        $this->actingAs($user)->get(route('verification.notice'))->assertOk();
        $this->post(route('verification.send'))->assertRedirect();
        Notification::assertSentTo($user, VerifyEmail::class);
        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
        $this->get($verificationUrl)->assertRedirect('/dashboard?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->get('/dashboard')->assertRedirect('/onboarding');
        $this->get(route('verification.notice'))->assertRedirect('/dashboard');
    }

    public function test_discord_install_and_callback_are_accessible_to_a_gated_user(): void
    {
        Http::preventStrayRequests();
        config(['services.discord.client_id' => '123']);
        $user = User::factory()->create();
        DigestSettings::factory()->for($user)->create();
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/onboarding');

        $response = $this->get(route('discord.install'));
        $state = session('discord_install_state');
        $this->assertIsString($state);
        $response->assertRedirect(app(DiscordInstall::class)->authorizeUrl($state));
        $this->get(route('discord.callback', ['state' => $state, 'error' => 'access_denied']))
            ->assertRedirect(route('delivery.index'));
        Http::assertNothingSent();
    }

    #[DataProvider('onboardingRoutes')]
    public function test_onboarding_routes_require_authentication_and_verified_email(string $method, string $path, string $name): void
    {
        $this->assertSame(url($path), route($name));
        $this->call($method, $path)->assertRedirect(route('login'));

        $user = User::factory()->unverified()->create();
        DigestSettings::factory()->for($user)->create();
        $this->actingAs($user)->call($method, $path)->assertRedirect(route('verification.notice'));
    }

    public static function onboardingRoutes(): array
    {
        return [
            ['GET', '/onboarding', 'onboarding.show'],
            ['POST', '/onboarding/interests', 'onboarding.interests'],
            ['PUT', '/onboarding/sources', 'onboarding.sources'],
            ['PUT', '/onboarding/about', 'onboarding.about'],
            ['POST', '/onboarding/ai-key', 'onboarding.ai-key'],
            ['POST', '/onboarding/ai-key/test', 'onboarding.ai-key.test'],
            ['POST', '/onboarding/complete', 'onboarding.complete'],
        ];
    }
}
