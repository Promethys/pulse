<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\SourceFetcher;
use App\Jobs\CheckCatalogSource;
use App\Models\Source;
use App\Models\SourceCheck;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckCatalogSourceTest extends TestCase
{
    use RefreshDatabase;

    public int $fetches = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-03-01 00:00:00', 'UTC'));
        $this->app->instance(FeedFetcher::class, new class($this) implements SourceFetcher
        {
            public function __construct(private CheckCatalogSourceTest $test) {}

            public function fetch(Source $source, CarbonImmutable $since): array
            {
                $this->test->fetches++;

                return [];
            }
        });
    }

    public function test_is_queued_with_one_attempt_and_a_two_minute_timeout(): void
    {
        $job = new CheckCatalogSource(Source::factory()->create());

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame(1, $job->tries);
        $this->assertSame(120, $job->timeout);
    }

    #[DataProvider('checkResults')]
    public function test_skips_a_check_created_this_month_while_the_job_waited(bool $ok): void
    {
        $source = Source::factory()->create();
        $job = unserialize(serialize(new CheckCatalogSource($source)));
        SourceCheck::factory()->for($source)->create(['checked_at' => now(), 'ok' => $ok]);

        $this->app->call([$job, 'handle']);

        $this->assertSame(0, $this->fetches);
        $this->assertDatabaseCount('source_checks', 1);
    }

    public function test_checks_again_after_the_utc_month_changes(): void
    {
        $source = Source::factory()->create();
        SourceCheck::factory()->for($source)->create(['checked_at' => now()->subSecond()]);

        $this->app->call([new CheckCatalogSource($source), 'handle']);

        $this->assertSame(1, $this->fetches);
        $this->assertDatabaseCount('source_checks', 2);
    }

    public function test_force_bypasses_the_month_guard(): void
    {
        $source = Source::factory()->create();
        SourceCheck::factory()->for($source)->create(['checked_at' => now()]);

        $this->app->call([new CheckCatalogSource($source, true), 'handle']);

        $this->assertSame(1, $this->fetches);
        $this->assertDatabaseCount('source_checks', 2);
    }

    #[DataProvider('forceOptions')]
    public function test_skips_a_source_disabled_after_dispatch_even_when_forced(bool $force): void
    {
        $source = Source::factory()->create();
        $job = new CheckCatalogSource($source, $force);
        Source::query()->whereKey($source->id)->update(['enabled' => false]);
        $job = unserialize(serialize($job));

        $this->app->call([$job, 'handle']);

        $this->assertSame(0, $this->fetches);
        $this->assertDatabaseCount('source_checks', 0);
    }

    /** @return array<string, array{bool}> */
    public static function checkResults(): array
    {
        return ['successful check' => [true], 'failed check' => [false]];
    }

    /** @return array<string, array{bool}> */
    public static function forceOptions(): array
    {
        return ['normal' => [false], 'forced' => [true]];
    }
}
