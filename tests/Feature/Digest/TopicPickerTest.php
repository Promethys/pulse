<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Learning\TopicPicker;
use App\Models\LearningTopic;
use Carbon\CarbonImmutable;
use Database\Seeders\LearningTopicTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopicPickerTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->today = CarbonImmutable::parse('2026-10-16');
    }

    public function test_picks_unused_topics_first_in_id_order(): void
    {
        $first = LearningTopic::factory()->create(['category' => 'databases']);
        LearningTopic::factory()->create(['category' => 'testing']);
        LearningTopic::factory()->create(['category' => 'security', 'last_used_on' => '2026-01-01']);

        $picked = (new TopicPicker)->next($this->today);

        $this->assertTrue($picked->is($first));
        $this->assertSame('2026-10-16', $picked->refresh()->last_used_on?->toDateString());
    }

    public function test_avoids_the_same_category_twice_in_a_row(): void
    {
        LearningTopic::factory()->create(['category' => 'databases', 'last_used_on' => '2026-10-15']);
        LearningTopic::factory()->create(['category' => 'databases']);
        $testing = LearningTopic::factory()->create(['category' => 'testing']);

        $this->assertTrue((new TopicPicker)->next($this->today)->is($testing));
    }

    public function test_same_category_is_allowed_when_nothing_else_is_left(): void
    {
        LearningTopic::factory()->create(['category' => 'databases', 'last_used_on' => '2026-10-15']);
        $only = LearningTopic::factory()->create(['category' => 'databases']);

        $this->assertTrue((new TopicPicker)->next($this->today)->is($only));
    }

    public function test_restarts_from_the_oldest_when_all_are_used(): void
    {
        LearningTopic::factory()->create(['category' => 'testing', 'last_used_on' => '2026-10-15']);
        $oldest = LearningTopic::factory()->create(['category' => 'databases', 'last_used_on' => '2026-09-01']);
        LearningTopic::factory()->create(['category' => 'security', 'last_used_on' => '2026-10-01']);

        $this->assertTrue((new TopicPicker)->next($this->today)->is($oldest));
    }

    public function test_reuses_the_topic_already_picked_today(): void
    {
        LearningTopic::factory()->create(['category' => 'testing']);
        $today = LearningTopic::factory()->create(['category' => 'databases', 'last_used_on' => '2026-10-16']);

        $this->assertTrue((new TopicPicker)->next($this->today)->is($today));
    }

    public function test_seeder_is_idempotent_and_covers_every_category(): void
    {
        $this->seed(LearningTopicTableSeeder::class);
        LearningTopic::query()->first()?->update(['last_used_on' => '2026-10-01']);
        $this->seed(LearningTopicTableSeeder::class);

        $count = LearningTopic::query()->count();
        $this->assertGreaterThanOrEqual(60, $count);
        $this->assertSame(1, LearningTopic::query()->whereNotNull('last_used_on')->count());
        $this->assertEqualsCanonicalizing(
            ['architecture', 'databases', 'distributed-systems', 'testing', 'performance', 'security', 'php-internals', 'laravel-internals', 'devops', 'design-patterns', 'ai-engineering'],
            LearningTopic::query()->distinct()->pluck('category')->all(),
        );
    }
}
