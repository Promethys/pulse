<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Learning\LearningPipeline;
use App\Digest\News\NewsPipeline;
use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class DigestTickTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['digest.timezone' => 'Europe/Paris']);
    }

    public function test_runs_due_pipelines_in_order_for_the_local_day(): void
    {
        CarbonImmutable::setTestNow('2026-07-01 07:30:00');
        $this->expectRun(NewsPipeline::class, 'news');
        $this->expectRun(LearningPipeline::class, 'learning');

        $this->artisan('digest:tick')->assertExitCode(0);

        $this->assertSame(['news:2026-07-01', 'learning:2026-07-01'], $this->calls);
    }

    public function test_nothing_due_runs_nothing(): void
    {
        CarbonImmutable::setTestNow('2026-12-01 07:00:00');
        $this->mock(NewsPipeline::class)->shouldNotReceive('run');
        $this->mock(LearningPipeline::class)->shouldNotReceive('run');

        $this->artisan('digest:tick')->assertExitCode(0);
    }

    public function test_a_news_failure_does_not_block_learning(): void
    {
        CarbonImmutable::setTestNow('2026-07-01 07:30:00');
        $this->expectRun(NewsPipeline::class, 'news', fail: true);
        $this->expectRun(LearningPipeline::class, 'learning');

        $this->artisan('digest:tick')
            ->expectsOutputToContain('news failed: Discord is down')
            ->assertExitCode(0);

        $this->assertSame(['news:2026-07-01', 'learning:2026-07-01'], $this->calls);
    }

    public function test_fails_when_every_due_pipeline_failed(): void
    {
        CarbonImmutable::setTestNow('2026-07-01 07:00:00');
        $this->expectRun(NewsPipeline::class, 'news', fail: true);

        $this->artisan('digest:tick')->assertExitCode(1);
    }

    public function test_prunes_old_skipped_items_only(): void
    {
        CarbonImmutable::setTestNow('2026-12-01 07:00:00');
        $this->mock(NewsPipeline::class)->shouldNotReceive('run');
        $this->mock(LearningPipeline::class)->shouldNotReceive('run');
        $old = Item::factory()->create(['status' => ItemStatus::Skipped, 'created_at' => '2026-10-15 07:00:00']);
        $recent = Item::factory()->create(['status' => ItemStatus::Skipped, 'created_at' => '2026-11-15 07:00:00']);
        $oldSelected = Item::factory()->create(['status' => ItemStatus::Selected, 'created_at' => '2026-10-15 07:00:00']);
        $oldCandidate = Item::factory()->create(['status' => ItemStatus::Candidate, 'created_at' => '2026-10-15 07:00:00']);

        $this->artisan('digest:tick')->assertExitCode(0);

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertModelExists($oldSelected);
        $this->assertModelExists($oldCandidate);
    }

    /**
     * @param  class-string  $class
     */
    private function expectRun(string $class, string $name, bool $fail = false): void
    {
        $this->mock($class, function (MockInterface $mock) use ($name, $fail) {
            $mock->shouldReceive('run')->once()->andReturnUsing(function (CarbonImmutable $today) use ($name, $fail) {
                $this->calls[] = "{$name}:{$today->toDateString()}";

                return $fail ? throw new RuntimeException('Discord is down') : new Post;
            });
        });
    }
}
