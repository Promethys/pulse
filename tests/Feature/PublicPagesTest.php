<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('pages')]
    public function test_guests_can_read_public_pages(string $url, string $component): void
    {
        $this->get($url)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component)
                ->where('auth.user', null)
                ->where('canRegister', true));
    }

    #[DataProvider('pages')]
    public function test_public_pages_do_not_require_verification_or_onboarding(string $url, string $component): void
    {
        $this->actingAs(User::factory()->unverified()->create())->get($url)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }

    #[DataProvider('registrationStates')]
    public function test_registration_availability_is_shared_on_public_pages(bool $enabled): void
    {
        config(['fortify.features' => $enabled ? [Features::registration()] : []]);

        $this->get(route('features'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Features')->where('canRegister', $enabled));
        $this->get(route('home'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Welcome')->where('canRegister', $enabled));
    }

    #[DataProvider('invalidDocsPaths')]
    public function test_docs_rejects_paths_that_are_not_lowercase_slugs(string $url): void
    {
        $this->get($url)->assertNotFound();
    }

    public static function pages(): array
    {
        return [
            'features' => ['/features', 'Features'],
            'open source' => ['/open-source', 'OpenSource'],
            'privacy' => ['/privacy', 'legal/Privacy'],
            'terms' => ['/terms', 'legal/Terms'],
            'docs index' => ['/docs', 'docs/Index'],
            'docs article' => ['/docs/delivery/add-pulse-to-discord', 'docs/Show'],
        ];
    }

    public static function registrationStates(): array
    {
        return ['open' => [true], 'closed' => [false]];
    }

    public static function invalidDocsPaths(): array
    {
        return [
            'uppercase section' => ['/docs/Delivery/article'],
            'uppercase article' => ['/docs/delivery/Article'],
            'underscore' => ['/docs/delivery/add_pulse'],
            'leading hyphen' => ['/docs/-delivery/article'],
            'trailing hyphen' => ['/docs/delivery/article-'],
            'repeated hyphen' => ['/docs/delivery/add--pulse'],
            'extra segment' => ['/docs/delivery/article/extra'],
        ];
    }
}
