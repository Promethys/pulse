<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Jobs\CheckCatalogSource;
use App\Models\Source;
use App\Models\SourceCheck;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogRefreshCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-03-01 00:00:00', 'UTC'));
        Bus::fake([CheckCatalogSource::class]);
    }

    public function test_dispatches_only_enabled_catalog_sources_without_a_check_in_the_current_utc_month(): void
    {
        config(['app.timezone' => 'America/Los_Angeles']);
        $unchecked = Source::factory()->create();
        $previousMonth = Source::factory()->create();
        SourceCheck::factory()->for($previousMonth)->create(['checked_at' => now()->subSecond(), 'ok' => false]);
        foreach ([true, false] as $ok) {
            $checked = Source::factory()->create();
            SourceCheck::factory()->for($checked)->create(['checked_at' => now(), 'ok' => $ok]);
        }
        $futureMonth = Source::factory()->create();
        SourceCheck::factory()->for($futureMonth)->create(['checked_at' => now()->addMonth()->startOfMonth()]);
        Source::factory()->create(['enabled' => false]);
        Source::factory()->for(User::factory())->create();

        $this->artisan('catalog:refresh')->expectsOutputToContain('Dispatched 3')->assertExitCode(0);

        Bus::assertDispatchedTimes(CheckCatalogSource::class, 3);
        foreach ([$unchecked, $previousMonth, $futureMonth] as $source) {
            Bus::assertDispatched(CheckCatalogSource::class, fn (CheckCatalogSource $job) => $job->source->is($source) && ! $job->force);
        }
        $this->assertDatabaseCount('source_checks', 4);
        $this->assertFalse(collect(app(Schedule::class)->events())
            ->contains(fn ($event) => str_contains((string) $event->command, 'catalog:refresh')));
    }

    public function test_a_check_earlier_this_month_is_not_due_even_after_thirty_days(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-31 23:59:59', 'UTC'));
        $source = Source::factory()->create();
        SourceCheck::factory()->for($source)->create(['checked_at' => '2026-03-01 00:00:00']);

        $this->artisan('catalog:refresh')->expectsOutputToContain('Dispatched 0')->assertExitCode(0);

        Bus::assertNothingDispatched();
    }

    #[DataProvider('checkResults')]
    public function test_force_dispatches_checked_enabled_catalog_sources_only(bool $ok): void
    {
        $source = Source::factory()->create();
        SourceCheck::factory()->for($source)->create(['checked_at' => now(), 'ok' => $ok]);
        $unchecked = Source::factory()->create();
        Source::factory()->create(['enabled' => false]);
        Source::factory()->for(User::factory())->create();

        $this->artisan('catalog:refresh', ['--force' => true])->expectsOutputToContain('Dispatched 2')->assertExitCode(0);

        Bus::assertDispatchedTimes(CheckCatalogSource::class, 2);
        foreach ([$source, $unchecked] as $expected) {
            Bus::assertDispatched(CheckCatalogSource::class, fn (CheckCatalogSource $job) => $job->source->is($expected) && $job->force);
        }
    }

    /** @return array<string, array{bool}> */
    public static function checkResults(): array
    {
        return ['successful check' => [true], 'failed check' => [false]];
    }
}
