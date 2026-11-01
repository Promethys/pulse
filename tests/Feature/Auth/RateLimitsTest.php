<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RateLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_many_addresses_cannot_keep_guessing_one_account(): void
    {
        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$attempt}"])
                ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])
                ->assertRedirect();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.200'])
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])
            ->assertTooManyRequests();
    }

    public function test_one_address_cannot_guess_many_accounts(): void
    {
        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->post(route('login.store'), ['email' => "user{$attempt}@example.test", 'password' => 'wrong'])
                ->assertRedirect();
        }

        $this->post(route('login.store'), ['email' => 'another@example.test', 'password' => 'wrong'])
            ->assertTooManyRequests();
    }

    public function test_an_ipv6_network_counts_as_one_address(): void
    {
        $first = $this->limitKeys('login', ['email' => 'a@example.test'], '2001:db8:1:2::1');
        $second = $this->limitKeys('login', ['email' => 'a@example.test'], '2001:db8:1:2:ffff::9');

        $this->assertSame($first, $second);
    }

    public function test_an_array_email_is_a_validation_error_not_a_server_error(): void
    {
        $this->post(route('login.store'), ['email' => ['x'], 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
    }

    public function test_a_random_credential_id_does_not_reset_the_passkey_limit(): void
    {
        $this->assertSame(
            $this->limitKeys('passkeys', ['credential' => ['id' => 'first']]),
            $this->limitKeys('passkeys', ['credential' => ['id' => 'second']]),
        );
    }

    public function test_two_factor_requests_without_a_pending_login_do_not_share_a_counter(): void
    {
        $this->assertNotSame(
            $this->limitKeys('two-factor', [], session: 'one'),
            $this->limitKeys('two-factor', [], session: 'two'),
        );
    }

    public function test_one_address_cannot_submit_registration_in_a_burst(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('register.store'))->assertRedirect();
        }

        $this->post(route('register.store'))->assertTooManyRequests();
    }

    public function test_one_address_cannot_keep_registering_through_the_hour(): void
    {
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->travel(13)->seconds();
            $this->post(route('register.store'))->assertRedirect();
        }

        $this->travel(13)->seconds();
        $this->post(route('register.store'))->assertTooManyRequests();
    }

    public function test_registrations_from_many_addresses_stop_at_the_daily_mail_budget(): void
    {
        Notification::fake();

        for ($attempt = 1; $attempt <= 50; $attempt++) {
            $this->register("user{$attempt}@example.test", "198.51.100.{$attempt}")->assertRedirect();
            Auth::logout();
        }

        $this->register('late@example.test', '198.51.100.200')->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_rejected_registrations_do_not_spend_the_mail_budget(): void
    {
        for ($attempt = 1; $attempt <= 60; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$attempt}"])
                ->post(route('register.store'))
                ->assertRedirect();
        }

        $this->register('real@example.test', '198.51.100.200');

        $this->assertAuthenticated();
    }

    public function test_one_address_cannot_request_reset_links_in_a_burst(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('password.email'), ['email' => "user{$attempt}@example.test"])->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'another@example.test'])->assertTooManyRequests();
    }

    public function test_one_address_cannot_keep_requesting_reset_links_through_the_hour(): void
    {
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->travel(13)->seconds();
            $this->post(route('password.email'), ['email' => "user{$attempt}@example.test"])->assertRedirect();
        }

        $this->travel(13)->seconds();
        $this->post(route('password.email'), ['email' => 'another@example.test'])->assertTooManyRequests();
    }

    public function test_many_addresses_cannot_flood_one_account_with_reset_links(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->travel(61)->seconds();
            $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$attempt}"])
                ->post(route('password.email'), ['email' => $user->email])
                ->assertRedirect();
        }

        $this->travel(61)->seconds();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.200'])
            ->post(route('password.email'), ['email' => $user->email])
            ->assertTooManyRequests();

        Notification::assertSentToTimes($user, ResetPassword::class, 5);
    }

    public function test_the_reset_link_account_limit_ignores_case(): void
    {
        $this->assertSame(
            $this->limitKeys('forgot-password', ['email' => 'Reader@Example.test']),
            $this->limitKeys('forgot-password', ['email' => 'reader@example.test']),
        );
    }

    public function test_reset_links_from_many_addresses_stop_at_the_daily_mail_budget(): void
    {
        Notification::fake();
        $users = User::factory()->count(51)->create();

        foreach ($users->take(50)->values() as $index => $user) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.($index + 1)])
                ->post(route('password.email'), ['email' => $user->email])
                ->assertRedirect();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.200'])
            ->post(route('password.email'), ['email' => $users->last()?->email])
            ->assertTooManyRequests();
    }

    public function test_unknown_emails_do_not_spend_the_reset_mail_budget(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 60; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$attempt}"])
                ->post(route('password.email'), ['email' => "nobody{$attempt}@example.test"])
                ->assertRedirect();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.200'])
            ->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_one_address_cannot_submit_password_resets_in_a_burst(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('password.update'))->assertRedirect();
        }

        $this->post(route('password.update'))->assertTooManyRequests();
    }

    public function test_one_account_cannot_keep_resending_the_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        for ($attempt = 1; $attempt <= 12; $attempt++) {
            $this->travel(13)->seconds();
            $this->actingAs($user)->post(route('verification.send'))->assertRedirect();
        }

        $this->travel(13)->seconds();
        $this->actingAs($user)->post(route('verification.send'))->assertTooManyRequests();
    }

    public function test_verification_limits_are_per_account(): void
    {
        $this->assertNotSame(
            $this->limitKeys('verification', [], user: User::factory()->make(['id' => 1])),
            $this->limitKeys('verification', [], user: User::factory()->make(['id' => 2])),
        );
    }

    private function register(string $email, string $ip): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->post(route('register.store'), [
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    private function limitKeys(string $limiter, array $input, string $ip = '198.51.100.1', string $session = 'session', ?User $user = null): array
    {
        $request = Request::create('/', 'POST', $input, server: ['REMOTE_ADDR' => $ip]);
        $store = new Store('test', new ArraySessionHandler(60), str_pad($session, 40, 'x'));
        $request->setLaravelSession($store);
        $request->setUserResolver(fn () => $user);
        $limits = (RateLimiter::limiter($limiter))($request);

        return array_map(fn ($limit) => (string) $limit->key, is_array($limits) ? $limits : [$limits]);
    }
}
