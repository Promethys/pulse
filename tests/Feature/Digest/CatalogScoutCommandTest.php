<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Enums\InterestStatus;
use App\Jobs\ScoutInterest;
use App\Models\Interest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class CatalogScoutCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-31 12:00:00', 'UTC'));
        Bus::fake([ScoutInterest::class]);
    }

    public function test_manual_dispatch_ignores_the_month_for_live_interests_and_reports_the_count(): void
    {
        $never = Interest::factory()->create(['status' => InterestStatus::Live]);
        $current = Interest::factory()->create(['status' => InterestStatus::Live, 'scouted_at' => now()]);
        Interest::factory()->create(['status' => InterestStatus::Draft]);

        $this->artisan('catalog:scout')->expectsOutputToContain('Dispatched 2')->assertExitCode(0);

        Bus::assertDispatchedTimes(ScoutInterest::class, 2);
        foreach ([$never, $current] as $interest) {
            Bus::assertDispatched(ScoutInterest::class, fn (ScoutInterest $job) => $job->interest->is($interest) && $job->max === 5);
        }
        $this->assertNull($never->fresh()->scouted_at);
        $this->assertTrue($current->fresh()->scouted_at->equalTo(now()));
    }

    public function test_interest_option_dispatches_only_the_requested_draft_even_if_already_scouted_with_the_requested_maximum(): void
    {
        $draft = Interest::factory()->create(['slug' => 'astronomy', 'status' => InterestStatus::Draft, 'scouted_at' => now()]);
        Interest::factory()->create(['status' => InterestStatus::Live]);
        Interest::factory()->create(['status' => InterestStatus::Draft]);

        $this->artisan('catalog:scout', ['--interest' => 'astronomy', '--max' => 2])
            ->expectsOutputToContain('Dispatched 1')->assertExitCode(0);

        Bus::assertDispatchedTimes(ScoutInterest::class, 1);
        Bus::assertDispatched(ScoutInterest::class, fn (ScoutInterest $job) => $job->interest->is($draft) && $job->max === 2);
    }

    public function test_unknown_interest_is_reported_as_a_failure_without_dispatching_other_interests(): void
    {
        Interest::factory()->create(['status' => InterestStatus::Live]);

        $this->artisan('catalog:scout', ['--interest' => 'missing-interest'])
            ->expectsOutputToContain('missing-interest')->assertExitCode(1);

        Bus::assertNothingDispatched();
    }

    public function test_reports_zero_when_no_interests_are_eligible(): void
    {
        Interest::factory()->create(['status' => InterestStatus::Draft]);

        $this->artisan('catalog:scout')->expectsOutputToContain('Dispatched 0')->assertExitCode(0);

        Bus::assertNothingDispatched();
    }
}
