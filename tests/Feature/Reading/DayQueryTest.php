<?php

declare(strict_types=1);

namespace Tests\Feature\Reading;

use App\Enums\LearningKind;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\LearningPost;
use App\Models\LearningTopic;
use App\Models\Post;
use App\Models\PostItem;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use App\Reading\Day;
use App\Reading\DayQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DayQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_post_returns_null_even_when_another_user_has_posts_and_items(): void
    {
        $user = User::factory()->create();
        $other = Post::factory()->create(['date' => '2026-10-20']);
        PostItem::factory()->create(['post_id' => $other->id]);
        LearningPost::query()->create(['post_id' => $other->id, 'kind' => LearningKind::Tip, 'title' => 'Private', 'body' => 'Private', 'links' => []]);

        $this->assertNull(app(DayQuery::class)->for($user, '2026-10-20'));
    }

    #[DataProvider('newsStates')]
    public function test_news_status_and_quiet_days(PostStatus $status): void
    {
        $post = Post::factory()->create(['date' => '2026-10-20', 'status' => $status]);
        $day = app(DayQuery::class)->for($post->user, CarbonImmutable::parse('2026-10-20'));

        $this->assertInstanceOf(Day::class, $day);
        $this->assertTrue((new \ReflectionClass($day))->isReadOnly());
        $this->assertSame('2026-10-20', $day->date);
        $this->assertSame($status, $day->news['status']);
        $this->assertSame([], $day->news['sections']);
        $this->assertSame(0, $day->news['item_count']);
        $this->assertNull($day->learning);
    }

    #[DataProvider('unfinishedStates')]
    public function test_pending_and_failed_news_do_not_expose_partial_sections(PostStatus $status): void
    {
        $post = Post::factory()->create(['date' => '2026-10-20', 'status' => $status]);
        PostItem::factory()->create(['post_id' => $post->id]);
        $day = app(DayQuery::class)->for($post->user, '2026-10-20');

        $this->assertSame($status, $day->news['status']);
        $this->assertSame([], $day->news['sections']);
    }

    public static function unfinishedStates(): array
    {
        return ['pending' => [PostStatus::Pending], 'failed' => [PostStatus::Failed]];
    }

    public static function newsStates(): array
    {
        return ['pending' => [PostStatus::Pending], 'failed' => [PostStatus::Failed], 'quiet' => [PostStatus::Generated]];
    }

    public function test_generated_items_are_grouped_in_position_order_with_relations_loaded(): void
    {
        $post = Post::factory()->create(['date' => '2026-10-20', 'status' => PostStatus::Generated]);
        $tag = Tag::factory()->create(['slug' => 'php', 'name' => 'PHP']);
        $later = PostItem::factory()->create(['post_id' => $post->id, 'position' => 9]);
        $first = PostItem::factory()->create(['post_id' => $post->id, 'position' => 2]);
        foreach ([$later, $first] as $item) {
            $item->tags()->attach($tag);
            $item->item->sources()->attach(Source::factory()->create(), ['first_seen_at' => now()]);
        }
        $private = PostItem::factory()->create(['post_id' => Post::factory()->create(['date' => '2026-10-20'])->id]);
        $private->tags()->attach($tag);
        $day = app(DayQuery::class)->for($post->user, '2026-10-20');

        $this->assertSame(2, $day->news['item_count']);
        $this->assertCount(1, $day->news['sections']);
        $section = $day->news['sections'][0];
        $this->assertSame('php', $section['slug']);
        $this->assertSame('PHP', $section['name']);
        $this->assertSame([$first->id, $later->id], array_map(fn (PostItem $item) => $item->id, $section['items']));
        foreach ($section['items'] as $item) {
            $this->assertTrue($item->relationLoaded('post'));
            $this->assertTrue($item->relationLoaded('item'));
            $this->assertTrue($item->relationLoaded('tags'));
            $this->assertTrue($item->item->relationLoaded('sources'));
        }
    }

    #[DataProvider('learningStates')]
    public function test_learning_components_and_status_are_scoped_to_the_owner(PostStatus $status, bool $tip, bool $topic): void
    {
        $post = Post::factory()->create(['date' => '2026-10-20', 'kind' => PostKind::Learning, 'status' => $status]);
        $expected = ['tip' => null, 'topic' => null];
        foreach ([LearningKind::Tip->value => $tip, LearningKind::Topic->value => $topic] as $kind => $present) {
            if ($present) {
                $entry = LearningPost::query()->create(['post_id' => $post->id, 'kind' => $kind, 'learning_topic_id' => LearningTopic::factory()->create()->id, 'title' => $kind, 'body' => 'Body', 'links' => []]);
                $entry->tags()->attach(Tag::factory()->create());
                $expected[$kind] = $entry->fresh(['topic', 'tags']);
            }
        }
        $other = Post::factory()->create(['date' => '2026-10-20', 'kind' => PostKind::Learning]);
        LearningPost::query()->create(['post_id' => $other->id, 'kind' => LearningKind::Tip, 'title' => 'Private', 'body' => 'Private', 'links' => []]);
        $day = app(DayQuery::class)->for($post->user, '2026-10-20');

        $this->assertNull($day->news);
        $this->assertSame($status, $day->learning['status']);
        $this->assertEquals($expected['tip'], $day->learning['tip']);
        $this->assertEquals($expected['topic'], $day->learning['topic']);
    }

    public static function learningStates(): array
    {
        return [
            'pending neither' => [PostStatus::Pending, false, false],
            'failed neither' => [PostStatus::Failed, false, false],
            'generated neither' => [PostStatus::Generated, false, false],
            'tip only' => [PostStatus::Generated, true, false],
            'topic only' => [PostStatus::Generated, false, true],
            'both' => [PostStatus::Generated, true, true],
        ];
    }

    public function test_navigation_uses_distinct_owned_dates_and_skips_gaps(): void
    {
        $user = User::factory()->create();
        foreach (['2026-10-01', '2026-10-10', '2026-10-29'] as $date) {
            Post::factory()->create(['user_id' => $user->id, 'date' => $date]);
        }
        Post::factory()->create(['user_id' => $user->id, 'date' => '2026-10-10', 'kind' => PostKind::Learning]);
        Post::factory()->create(['date' => '2026-10-09']);
        Post::factory()->create(['date' => '2026-10-11']);
        foreach ([['2026-10-01', null, '2026-10-10'], ['2026-10-10', '2026-10-01', '2026-10-29'], ['2026-10-29', '2026-10-10', null]] as [$date, $previous, $next]) {
            $day = app(DayQuery::class)->for($user, $date);
            $this->assertSame($previous, $day->previous_date);
            $this->assertSame($next, $day->next_date);
        }
    }
}
