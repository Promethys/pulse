<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\RateLimiter;
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

    /**
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    private function limitKeys(string $limiter, array $input, string $ip = '198.51.100.1', string $session = 'session'): array
    {
        $request = Request::create('/', 'POST', $input, server: ['REMOTE_ADDR' => $ip]);
        $store = new Store('test', new ArraySessionHandler(60), str_pad($session, 40, 'x'));
        $request->setLaravelSession($store);
        $limits = (RateLimiter::limiter($limiter))($request);

        return array_map(fn ($limit) => (string) $limit->key, is_array($limits) ? $limits : [$limits]);
    }
}
