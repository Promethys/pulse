<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Enums\InterestCategory;
use App\Enums\InterestStatus;
use App\Models\Interest;
use Database\Seeders\InterestTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterestTableSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_53_draft_interests(): void
    {
        $this->seed(InterestTableSeeder::class);

        $this->assertSame(53, Interest::query()->count());
        $this->assertSame(53, Interest::query()->where('status', InterestStatus::Draft)->count());
    }

    public function test_seeds_exactly_the_learning_interests(): void
    {
        $this->seed(InterestTableSeeder::class);

        $this->assertSame(
            ['artificial-intelligence', 'data-science', 'devops', 'operating-systems', 'programming', 'programming-languages', 'security'],
            Interest::query()->where('has_learning', true)->orderBy('slug')->pluck('slug')->all(),
        );
    }

    public function test_positions_are_unique_and_contiguous(): void
    {
        $this->seed(InterestTableSeeder::class);

        $this->assertSame(range(1, 53), Interest::query()->orderBy('position')->pluck('position')->all());
    }

    public function test_every_category_has_interests(): void
    {
        $this->seed(InterestTableSeeder::class);

        foreach (InterestCategory::cases() as $category) {
            $this->assertTrue(Interest::query()->where('category', $category)->exists());
        }
    }

    public function test_reseeding_never_changes_existing_rows(): void
    {
        $this->seed(InterestTableSeeder::class);
        Interest::query()->where('slug', 'artificial-intelligence')->update(['name' => 'AI']);
        Interest::query()->where('slug', 'programming')->update(['status' => InterestStatus::Live]);
        $custom = Interest::factory()->create(['slug' => 'custom-interest']);

        $this->seed(InterestTableSeeder::class);

        $this->assertSame('AI', Interest::query()->where('slug', 'artificial-intelligence')->firstOrFail()->name);
        $this->assertSame(InterestStatus::Live, Interest::query()->where('slug', 'programming')->firstOrFail()->status);
        $this->assertModelExists($custom);
        $this->assertSame(54, Interest::query()->count());
    }
}
