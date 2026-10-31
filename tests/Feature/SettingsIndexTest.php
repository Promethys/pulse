<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_users_can_open_the_named_settings_index(): void
    {
        $this->assertSame(url('/settings'), route('settings.index'));

        $this->actingAs(User::factory()->onboarded()->create())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('settings/Index'));
    }

    public function test_guests_are_sent_to_login_from_the_named_settings_index(): void
    {
        $this->get(route('settings.index'))
            ->assertRedirect(route('login'));
    }

    public function test_unverified_users_must_verify_before_opening_settings(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get('/settings')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_dashboard_keeps_its_permanent_redirect_and_rejects_post_requests(): void
    {
        $this->actingAs(User::factory()->onboarded()->create())
            ->get(route('dashboard'))
            ->assertStatus(301)
            ->assertRedirect(route('home'));

        $this->post(route('dashboard'))->assertStatus(405);
    }
}
