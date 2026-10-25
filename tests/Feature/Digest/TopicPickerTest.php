<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Learning\TopicPicker;
use App\Models\LearningTopic;
use App\Models\LearningTopicUsage;
use App\Models\User;
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

    public function test_next_for_picks_never_used_topics_in_id_order(): void
    {
        $user = User::factory()->create();
        $first = LearningTopic::factory()->create(['category' => 'databases']);
        LearningTopic::factory()->create(['category' => 'testing']);

        $picked = (new TopicPicker)->nextFor($user, $this->today);

        $this->assertTrue($picked->is($first));
        $this->assertDatabaseHas('learning_topic_usages', [
            'user_id' => $user->id, 'learning_topic_id' => $first->id, 'used_on' => '2026-10-16',
        ]);
    }

    public function test_next_for_prefers_a_never_used_topic_over_an_older_used_topic(): void
    {
        $user = User::factory()->create();
        $used = LearningTopic::factory()->create(['category' => 'testing']);
        $unused = LearningTopic::factory()->create(['category' => 'testing']);
        $this->usage($user, $used, '2026-01-01');

        $this->assertTrue((new TopicPicker)->nextFor($user, $this->today)->is($unused));
    }

    public function test_next_for_avoids_the_previous_usage_category_even_after_a_gap(): void
    {
        $user = User::factory()->create();
        $previous = LearningTopic::factory()->create(['category' => 'databases']);
        LearningTopic::factory()->create(['category' => 'databases']);
        $testing = LearningTopic::factory()->create(['category' => 'testing']);
        $this->usage($user, $previous, '2026-10-01');

        $this->assertTrue((new TopicPicker)->nextFor($user, $this->today)->is($testing));
    }

    public function test_next_for_allows_the_previous_category_when_no_other_category_exists(): void
    {
        $user = User::factory()->create();
        $previous = LearningTopic::factory()->create(['category' => 'databases']);
        $unused = LearningTopic::factory()->create(['category' => 'databases']);
        $this->usage($user, $previous, '2026-10-15');

        $this->assertTrue((new TopicPicker)->nextFor($user, $this->today)->is($unused));
    }

    public function test_next_for_uses_each_topics_latest_usage_to_find_the_least_recently_used(): void
    {
        $user = User::factory()->create();
        $recent = LearningTopic::factory()->create(['category' => 'testing']);
        $oldest = LearningTopic::factory()->create(['category' => 'databases']);
        $previous = LearningTopic::factory()->create(['category' => 'security']);
        $this->usage($user, $recent, '2026-01-01');
        $this->usage($user, $oldest, '2026-09-01');
        $this->usage($user, $recent, '2026-10-01');
        $this->usage($user, $previous, '2026-10-15');

        $this->assertTrue((new TopicPicker)->nextFor($user, $this->today)->is($oldest));
    }

    public function test_next_for_reuses_todays_topic_without_changing_the_usage_on_retry(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        LearningTopic::factory()->create(['category' => 'testing']);
        $topic = LearningTopic::factory()->create(['category' => 'databases']);
        $usage = $this->usage($user, $topic, '2026-10-16');
        $updatedAt = $usage->updated_at;
        $this->travel(1)->seconds();

        $picker = new TopicPicker;
        $this->assertTrue($picker->nextFor($user, $this->today)->is($topic));
        $this->assertTrue($picker->nextFor($user, $this->today)->is($topic));
        $this->assertSame(1, $user->learningTopicUsages()->count());
        $this->assertTrue($updatedAt->equalTo($usage->refresh()->updated_at));
    }

    public function test_next_for_ignores_other_users_history_and_todays_pick(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $first = LearningTopic::factory()->create(['category' => 'databases']);
        $otherToday = LearningTopic::factory()->create(['category' => 'testing']);
        $this->usage($other, $first, '2026-10-15');
        $usage = $this->usage($other, $otherToday, '2026-10-16');

        $this->assertTrue((new TopicPicker)->nextFor($user, $this->today)->is($first));
        $this->assertSame(3, LearningTopicUsage::query()->count());
        $this->assertSame($otherToday->id, $usage->refresh()->learning_topic_id);
        $this->assertSame('2026-10-16', $usage->used_on->toDateString());
    }

    public function test_seeder_is_idempotent_and_covers_every_category(): void
    {
        $this->seed(LearningTopicTableSeeder::class);
        $before = LearningTopic::query()->orderBy('id')->get()->map(fn (LearningTopic $topic) => $topic->fresh()->getAttributes())->all();
        $this->seed(LearningTopicTableSeeder::class);

        $this->assertEquals($before, LearningTopic::query()->orderBy('id')->get()->map(fn (LearningTopic $topic) => $topic->fresh()->getAttributes())->all());

        $count = LearningTopic::query()->count();
        $this->assertGreaterThanOrEqual(60, $count);
        $this->assertEqualsCanonicalizing(
            ['architecture', 'databases', 'distributed-systems', 'testing', 'performance', 'security', 'php-internals', 'laravel-internals', 'devops', 'design-patterns', 'ai-engineering'],
            LearningTopic::query()->distinct()->pluck('category')->all(),
        );
    }

    private function usage(User $user, LearningTopic $topic, string $date): LearningTopicUsage
    {
        return LearningTopicUsage::factory()->create([
            'user_id' => $user->id, 'learning_topic_id' => $topic->id, 'used_on' => $date,
        ]);
    }
}
