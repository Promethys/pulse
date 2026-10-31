<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('home', absolute: false));
    }

    #[DataProvider('passwordPolicies')]
    public function test_password_hint_matches_the_active_policy(string $environment, string $hint, string $rules): void
    {
        $this->withoutVite();
        $this->app->instance('env', $environment);

        $this->get(route('register'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Register', false)
                ->where('passwordHint', $hint)
                ->where('passwordRules', $rules));
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
}
