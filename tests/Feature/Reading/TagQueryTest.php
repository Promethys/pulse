<?php

declare(strict_types=1);

namespace Tests\Feature\Reading;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostItem;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use App\Reading\TagQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

class TagQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_tag_has_twelve_zero_windows_and_no_other_users_items(): void
    {
        $tag = Tag::factory()->create();
        PostItem::factory()->create()->tags()->attach($tag);
        $result = app(TagQuery::class)->for(User::factory()->create(), $tag, CarbonImmutable::parse('2026-10-29'));

        $this->assertTrue($result['tag']->is($tag));
        $this->assertSame(0, $result['count']);
        $this->assertSame(array_fill(0, 12, 0), $result['weeks']);
        $this->assertInstanceOf(LengthAwarePaginator::class, $result['items']);
        $this->assertSame(0, $result['items']->total());
        $this->assertSame(30, $result['items']->perPage());
    }

    public function test_week_windows_are_inclusive_zero_filled_and_use_digest_dates(): void
    {
        $this->freezeSecond();
        $today = CarbonImmutable::parse('2026-10-29');
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        foreach ([0, 6, 7, 13, 77, 83, 84, -1] as $daysAgo) {
            $this->taggedItem($user, $tag, $today->subDays($daysAgo)->toDateString());
        }
        $this->taggedItem(User::factory()->create(), $tag, $today->toDateString());
        $this->taggedItem($user, Tag::factory()->create(), $today->subDays(1)->toDateString());
        $result = app(TagQuery::class)->for($user, $tag, $today);

        $this->assertSame(8, $result['count']);
        $this->assertSame([2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 2], $result['weeks']);
        $this->assertSame(8, $result['items']->total());
    }

    public function test_items_are_paginated_by_digest_date_then_position_with_relations_loaded(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $old = $this->taggedItem($user, $tag, '2026-10-20');
        $later = $this->taggedItem($user, $tag, '2026-10-29', 9);
        $first = PostItem::factory()->create(['post_id' => $later->post_id, 'position' => 1]);
        $first->tags()->attach($tag);
        $first->item->sources()->attach(Source::factory()->create(), ['first_seen_at' => now()]);
        $this->taggedItem(User::factory()->create(), $tag, '2026-10-29');
        $result = app(TagQuery::class)->for($user, $tag, CarbonImmutable::parse('2026-10-29'), 2);

        $this->assertSame(3, $result['count']);
        $this->assertSame(3, $result['items']->total());
        $this->assertSame(2, $result['items']->perPage());
        $this->assertSame([$first->id, $later->id], $result['items']->getCollection()->modelKeys());
        foreach ($result['items']->items() as $item) {
            foreach (['item', 'tags', 'post'] as $relation) {
                $this->assertTrue($item->relationLoaded($relation));
            }
            $this->assertTrue($item->item->relationLoaded('sources'));
        }
        Paginator::currentPageResolver(fn () => 2);
        try {
            $second = app(TagQuery::class)->for($user, $tag, CarbonImmutable::parse('2026-10-29'), 2);
            $this->assertSame([$old->id], $second['items']->getCollection()->modelKeys());
            $this->assertSame(3, $second['count']);
        } finally {
            Paginator::currentPageResolver(fn () => 1);
        }
    }

    private function taggedItem(User $user, Tag $tag, string $date, int $position = 1): PostItem
    {
        $post = Post::factory()->create(['user_id' => $user->id, 'date' => $date, 'status' => PostStatus::Generated]);
        $item = PostItem::factory()->create(['post_id' => $post->id, 'position' => $position]);
        $item->tags()->attach($tag);

        return $item;
    }
}
