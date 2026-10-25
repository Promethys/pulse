<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InterestCategory;
use App\Enums\InterestStatus;
use App\Models\Interest;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterestTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_follow_interests(): void
    {
        $user = User::factory()->create();
        $interests = Interest::factory()->count(2)->create();

        $user->interests()->attach($interests->modelKeys());

        $this->assertEqualsCanonicalizing($interests->modelKeys(), $user->fresh()->interests->modelKeys());
        foreach ($interests as $interest) {
            $this->assertTrue($interest->users->sole()->is($user));
            $this->assertDatabaseHas('interest_user', [
                'interest_id' => $interest->id,
                'user_id' => $user->id,
            ]);
        }
        $this->assertSame(0, $user->interests()->wherePivotNull('created_at')->count());
        $this->assertSame(0, $user->interests()->wherePivotNull('updated_at')->count());
    }

    public function test_live_scope_returns_only_live_interests(): void
    {
        Interest::factory()->create();
        $live = Interest::factory()->create(['status' => InterestStatus::Live]);

        $this->assertSame([$live->id], Interest::query()->live()->pluck('id')->all());
    }

    public function test_deleting_a_user_removes_pivot_rows_and_keeps_interests(): void
    {
        $user = User::factory()->create();
        $interest = Interest::factory()->create();
        $otherUser = User::factory()->create();
        $interest->users()->attach([$user->id, $otherUser->id]);

        $user->delete();

        $this->assertDatabaseMissing('interest_user', ['user_id' => $user->id]);
        $this->assertDatabaseHas('interest_user', ['interest_id' => $interest->id, 'user_id' => $otherUser->id]);
        $this->assertModelExists($interest);
    }

    public function test_categories_have_display_labels(): void
    {
        $this->assertSame('Life', InterestCategory::Life->label());
        $this->assertSame('Software development', InterestCategory::SoftwareDevelopment->label());
    }

    public function test_statuses_count_only_enabled_default_catalog_sources(): void
    {
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $empty = Interest::factory()->create(['status' => InterestStatus::Live]);
        $sources = Source::factory()->count(2)->create(['is_default' => true]);
        $interest->sources()->attach($sources);
        $interest->sources()->attach(Source::factory()->create(['is_default' => true, 'enabled' => false]));
        $interest->sources()->attach(Source::factory()->create());
        $interest->sources()->attach(Source::factory()->for(User::factory())->create(['is_default' => true]));

        Interest::refreshStatuses();

        $this->assertSame(InterestStatus::Draft, $interest->fresh()->status);
        $this->assertSame(InterestStatus::Draft, $empty->fresh()->status);

        $source = Source::factory()->create(['is_default' => true]);
        $interest->sources()->attach($source);
        Interest::refreshStatuses();

        $this->assertSame(InterestStatus::Live, $interest->fresh()->status);

        $source->update(['enabled' => false]);
        Interest::refreshStatuses();

        $this->assertSame(InterestStatus::Draft, $interest->fresh()->status);
    }
}
