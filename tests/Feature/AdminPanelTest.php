<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_app_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_the_panel_has_no_login_of_its_own(): void
    {
        $this->assertFalse(Filament::getPanel('admin')->hasLogin());
        $this->get('/admin/login')->assertNotFound();
    }

    public function test_inertia_visits_become_a_full_page_load(): void
    {
        $admin = User::factory()->onboarded()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->get('/admin', ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', url('/admin'));
    }

    public function test_avatars_are_generated_locally(): void
    {
        $user = User::factory()->make(['name' => 'Ada Lovelace']);

        $url = Filament::getPanel('admin')->getDefaultAvatarProvider();
        $avatar = app($url)->get($user);

        $this->assertStringStartsWith('data:image/svg+xml', $avatar);
        $this->assertStringContainsString('AL', rawurldecode($avatar));
    }

    public function test_the_primary_palette_keeps_white_text_readable_on_shade_600(): void
    {
        $palette = Filament::getPanel('admin')->getColors()['primary'];

        $this->assertIsArray($palette);
        $this->assertSame('oklch(0.9 0.05 230)', $palette[200]);
        $this->assertTrue(Color::isTextContrastRatioAccessible($palette[600], '#ffffff'));
    }

    public function test_only_verified_admins_can_enter(): void
    {
        $admin = User::factory()->onboarded()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get('/admin')->assertOk();

        $this->actingAs(User::factory()->onboarded()->create())->get('/admin')->assertForbidden();

        $unverified = User::factory()->onboarded()->unverified()->create();
        $unverified->forceFill(['is_admin' => true])->save();
        $this->actingAs($unverified)->get('/admin')->assertForbidden();
    }

    public function test_the_panel_links_back_to_the_app(): void
    {
        $admin = User::factory()->onboarded()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->get('/admin')->assertSee('Back to Pulse');
    }

    public function test_the_app_only_tells_verified_admins_about_the_panel(): void
    {
        $admin = User::factory()->onboarded()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.canAccessAdmin', true));
        $this->actingAs(User::factory()->onboarded()->create())->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.canAccessAdmin', false));
    }
}
