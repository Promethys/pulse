<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    #[DataProvider('errors')]
    public function test_errors_use_laravel_in_debug_and_inertia_in_production(string $path, int $status): void
    {
        Route::get('/_test/forbidden', fn () => abort(403))->middleware('web');
        config(['app.debug' => true]);

        $this->get($path)
            ->assertStatus($status)
            ->assertHeaderMissing('X-Inertia');

        config(['app.debug' => false]);

        $this->get($path)
            ->assertStatus($status)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', $status));
    }

    public static function errors(): array
    {
        return [
            'missing route' => ['/_test/missing-task16b-page', 404],
            'forbidden route' => ['/_test/forbidden', 403],
        ];
    }
}
