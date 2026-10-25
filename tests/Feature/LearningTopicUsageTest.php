<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LearningTopic;
use App\Models\LearningTopicUsage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningTopicUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_belongs_to_its_user_and_topic_and_is_available_from_both(): void
    {
        $user = User::factory()->create();
        $topic = LearningTopic::factory()->create();
        $usage = new LearningTopicUsage;
        $usage->fill(['user_id' => $user->id, 'learning_topic_id' => $topic->id, 'used_on' => '2026-10-25']);
        $usage->save();
        LearningTopicUsage::factory()->create();

        $this->assertTrue($usage->fresh()->user->is($user));
        $this->assertTrue($usage->fresh()->topic->is($topic));
        $this->assertSame([$usage->id], $user->learningTopicUsages->modelKeys());
        $this->assertSame([$usage->id], $topic->usages->modelKeys());
    }

    public function test_a_user_cannot_use_two_topics_on_the_same_day(): void
    {
        $user = User::factory()->create();
        $topic = LearningTopic::factory()->create();
        $otherTopic = LearningTopic::factory()->create();
        LearningTopicUsage::factory()->create(['user_id' => $user->id, 'learning_topic_id' => $topic->id, 'used_on' => '2026-10-25']);

        $this->expectException(UniqueConstraintViolationException::class);

        LearningTopicUsage::factory()->create(['user_id' => $user->id, 'learning_topic_id' => $otherTopic->id, 'used_on' => '2026-10-25']);
    }

    public function test_two_users_can_use_the_same_topic_on_the_same_day(): void
    {
        $topic = LearningTopic::factory()->create();
        $first = LearningTopicUsage::factory()->create(['user_id' => User::factory()->create()->id, 'learning_topic_id' => $topic->id, 'used_on' => '2026-10-25']);
        $second = LearningTopicUsage::factory()->create(['user_id' => User::factory()->create()->id, 'learning_topic_id' => $topic->id, 'used_on' => '2026-10-25']);

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $topic->usages->modelKeys());
    }

    public function test_a_user_can_use_the_same_topic_on_different_days(): void
    {
        $first = LearningTopicUsage::factory()->create(['used_on' => '2026-10-25']);
        $second = LearningTopicUsage::factory()->create(['user_id' => $first->user_id, 'learning_topic_id' => $first->learning_topic_id, 'used_on' => '2026-10-26']);

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $first->user->learningTopicUsages->modelKeys());
    }

    public function test_deleting_a_user_deletes_their_usages_and_keeps_other_users_usages(): void
    {
        $usage = LearningTopicUsage::factory()->create();
        $other = LearningTopicUsage::factory()->create(['learning_topic_id' => $usage->learning_topic_id]);
        $topic = $usage->topic;

        $usage->user->delete();

        $this->assertModelMissing($usage);
        $this->assertModelExists($other);
        $this->assertModelExists($topic);
    }

    public function test_deleting_a_topic_deletes_its_usages_and_keeps_users(): void
    {
        $usage = LearningTopicUsage::factory()->create();
        $user = $usage->user;

        $usage->topic->delete();

        $this->assertModelMissing($usage);
        $this->assertModelExists($user);
    }

    public function test_used_on_is_cast_to_an_immutable_date(): void
    {
        $usage = LearningTopicUsage::factory()->create(['used_on' => '2026-10-25']);

        $this->assertInstanceOf(CarbonImmutable::class, $usage->fresh()->used_on);
        $this->assertSame('2026-10-25 00:00:00', $usage->fresh()->used_on->format('Y-m-d H:i:s'));
    }
}
