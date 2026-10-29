<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Interest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_outside_local_seeds_the_catalog_without_a_test_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, Interest::query()->count());
        $this->assertSame(0, User::query()->count());
    }

    public function test_local_also_creates_the_test_user(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }
}
