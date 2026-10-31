<?php

declare(strict_types=1);

namespace Tests\Feature\Reading;

use App\Enums\LearningKind;
use App\Enums\PostKind;
use App\Models\LearningPost;
use App\Models\LearningTopic;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Reading\LearningQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LearningQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_learning_has_empty_list_and_filters(): void
    {
        $this->entry(User::factory()->create(), '2026-10-29', LearningKind::Tip, 'private', Tag::factory()->create());
        $user = User::factory()->create();
        $query = app(LearningQuery::class);
        $result = $query->list($user);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame(0, $result->total());
        $this->assertSame(30, $result->perPage());
        $this->assertSame([], $result->items());
        $this->assertSame(['categories' => [], 'tags' => []], $query->filters($user));
    }

    public function test_list_orders_by_date_then_tip_before_topic_and_paginates(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $old = $this->entry($user, '2026-10-01', LearningKind::Tip, 'testing', $tag);
        $topic = $this->entry($user, '2026-10-29', LearningKind::Topic, 'testing', $tag);
        $tip = $this->entry($user, '2026-10-29', LearningKind::Tip, 'testing', $tag);
        $this->entry(User::factory()->create(), '2026-10-29', LearningKind::Tip, 'testing', $tag);
        $result = app(LearningQuery::class)->list($user, perPage: 2);

        $this->assertSame(3, $result->total());
        $this->assertSame(2, $result->perPage());
        $this->assertSame([$tip->id, $topic->id], $result->getCollection()->modelKeys());
        foreach ($result->items() as $entry) {
            foreach (['post', 'topic', 'tags'] as $relation) {
                $this->assertTrue($entry->relationLoaded($relation));
            }
        }
        Paginator::currentPageResolver(fn () => 2);
        try {
            $this->assertSame([$old->id], app(LearningQuery::class)->list($user, perPage: 2)->getCollection()->modelKeys());
        } finally {
            Paginator::currentPageResolver(fn () => 1);
        }
    }

    #[DataProvider('filterStates')]
    public function test_filters_are_optional_and_combined_with_and(?LearningKind $kind, ?string $category, ?string $tagSlug, array $expected): void
    {
        $user = User::factory()->create();
        $php = Tag::factory()->create(['slug' => 'php']);
        $ai = Tag::factory()->create(['slug' => 'ai']);
        $entries = [
            $this->entry($user, '2026-10-29', LearningKind::Tip, 'testing', $php),
            $this->entry($user, '2026-10-28', LearningKind::Topic, 'testing', $php),
            $this->entry($user, '2026-10-27', LearningKind::Tip, 'architecture', $php),
            $this->entry($user, '2026-10-26', LearningKind::Tip, 'testing', $ai),
        ];
        $this->entry(User::factory()->create(), '2026-10-29', LearningKind::Tip, 'testing', $php);
        $result = app(LearningQuery::class)->list($user, $kind, $category, $tagSlug);

        $this->assertSame(array_map(fn (int $index) => $entries[$index]->id, $expected), $result->getCollection()->modelKeys());
        $this->assertSame(count($expected), $result->total());
    }

    public static function filterStates(): array
    {
        return [
            'kind' => [LearningKind::Tip, null, null, [0, 2, 3]],
            'category' => [null, 'testing', null, [0, 1, 3]],
            'tag' => [null, null, 'php', [0, 1, 2]],
            'all' => [LearningKind::Tip, 'testing', 'php', [0]],
            'no match' => [LearningKind::Tip, 'missing', 'php', []],
        ];
    }

    public function test_filters_are_distinct_sorted_and_exclude_other_users_categories_and_tags(): void
    {
        $user = User::factory()->create();
        $zulu = Tag::factory()->create(['name' => 'Zulu']);
        $alpha = Tag::factory()->create(['name' => 'Alpha']);
        $this->entry($user, '2026-10-29', LearningKind::Tip, 'testing', $zulu);
        $this->entry($user, '2026-10-28', LearningKind::Tip, 'architecture', $alpha);
        $this->entry($user, '2026-10-27', LearningKind::Tip, 'testing', $zulu);
        $this->entry(User::factory()->create(), '2026-10-29', LearningKind::Tip, 'private', Tag::factory()->create());
        $result = app(LearningQuery::class)->filters($user);

        $this->assertSame(['architecture', 'testing'], $result['categories']);
        $this->assertSame([$alpha->slug, $zulu->slug], collect($result['tags'])->pluck('slug')->all());
    }

    public function test_find_returns_only_an_owned_post_and_null_for_missing_or_foreign_ids(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $owned = $this->entry($user, '2026-10-29', LearningKind::Tip, null, $tag);
        $foreign = $this->entry(User::factory()->create(), '2026-10-29', LearningKind::Tip, null, $tag);
        $query = app(LearningQuery::class);
        $found = $query->find($user, $owned->id);

        $this->assertTrue($found->is($owned));
        $this->assertNull($query->find($user, $foreign->id));
        $this->assertNull($query->find($user, max($owned->id, $foreign->id) + 1));
    }

    private function entry(User $user, string $date, LearningKind $kind, ?string $category, Tag $tag): LearningPost
    {
        $post = Post::query()->firstOrCreate(['user_id' => $user->id, 'date' => $date, 'kind' => PostKind::Learning]);
        $entry = LearningPost::query()->create(['post_id' => $post->id, 'kind' => $kind, 'learning_topic_id' => $category === null ? null : LearningTopic::factory()->create(['category' => $category])->id, 'title' => 'Learning', 'body' => 'Body', 'links' => []]);
        $entry->tags()->attach($tag);

        return $entry;
    }
}
