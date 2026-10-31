<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $_ENV['REGISTRATION_ENABLED'] = $_SERVER['REGISTRATION_ENABLED'] = 'false';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($_ENV['REGISTRATION_ENABLED'], $_SERVER['REGISTRATION_ENABLED']);
    }

    public function test_the_registration_routes_do_not_exist(): void
    {
        $this->get('/register')->assertNotFound();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertSame(0, User::query()->count());
    }

    public function test_the_guest_pages_do_not_offer_registration(): void
    {
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->component('Welcome')->where('canRegister', false));

        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page->component('auth/Login')->where('canRegister', false));
    }
}
