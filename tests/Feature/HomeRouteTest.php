<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HomeRouteTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('readerStates')]
    public function test_home_remains_public_for_every_reader_state(string $state): void
    {
        if ($state !== 'guest') {
            $factory = User::factory();
            $user = match ($state) {
                'unverified' => $factory->unverified()->create(),
                'onboarded' => $factory->onboarded()->create(),
                default => $factory->create(),
            };
            $this->actingAs($user);
        }

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Welcome')
                ->where('canRegister', true)
                ->missing('day')
                ->missing('setup'));
    }

    public static function readerStates(): array
    {
        return [
            'guest' => ['guest'],
            'unverified' => ['unverified'],
            'not onboarded' => ['not onboarded'],
            'onboarded' => ['onboarded'],
        ];
    }

    public function test_dashboard_serves_today_without_redirecting(): void
    {
        $this->assertSame('/dashboard', route('dashboard', absolute: false));

        $this->actingAs(User::factory()->onboarded()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Today')
                ->where('day', null)
                ->where('available_dates', []));
    }

    public function test_guests_must_log_in_before_viewing_the_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_unverified_users_must_verify_before_viewing_the_dashboard(): void
    {
        $this->actingAs(User::factory()->onboarded()->unverified()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_users_must_complete_onboarding_before_viewing_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('onboarding.show'));
    }

    public function test_fortify_login_redirects_to_dashboard(): void
    {
        $user = User::factory()->onboarded()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
