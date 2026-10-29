<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_serves_the_landing_page_to_guests_and_today_to_verified_users(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Welcome'));

        $this->actingAs(User::factory()->onboarded()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Today'));
    }

    public function test_unverified_users_must_verify_before_viewing_today(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('home'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_dashboard_permanently_redirects_signed_in_users_to_home(): void
    {
        $this->actingAs(User::factory()->onboarded()->create())
            ->get(route('dashboard'))
            ->assertStatus(301)
            ->assertRedirect(route('home'));
    }

    public function test_fortify_login_redirects_to_home(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('home', absolute: false));
    }
}
