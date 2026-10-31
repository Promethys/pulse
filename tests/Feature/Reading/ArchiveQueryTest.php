<?php

declare(strict_types=1);

namespace Tests\Feature\Reading;

use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\LearningPost;
use App\Models\Post;
use App\Models\PostItem;
use App\Models\Tag;
use App\Models\User;
use App\Reading\ArchiveQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

class ArchiveQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_archive_excludes_other_users_posts_items_and_learning(): void
    {
        $post = Post::factory()->create();
        PostItem::factory()->create(['post_id' => $post->id]);
        LearningPost::query()->create(['post_id' => $post->id, 'kind' => 'tip', 'title' => 'Private', 'body' => 'Private', 'links' => []]);
        $result = app(ArchiveQuery::class)->for(User::factory()->create());

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame(0, $result->total());
        $this->assertSame(30, $result->perPage());
        $this->assertSame([], $result->items());
    }

    public function test_dates_are_paginated_once_with_counts_distinct_sorted_tags_and_statuses(): void
    {
        $user = User::factory()->create();
        $news = Post::factory()->create(['user_id' => $user->id, 'date' => '2026-10-29', 'status' => PostStatus::Generated]);
        Post::factory()->create(['user_id' => $user->id, 'date' => '2026-10-29', 'kind' => PostKind::Learning, 'status' => PostStatus::Failed]);
        Post::factory()->create(['user_id' => $user->id, 'date' => '2026-10-25', 'kind' => PostKind::Learning, 'status' => PostStatus::Pending]);
        Post::factory()->create(['user_id' => $user->id, 'date' => '2026-10-01', 'status' => PostStatus::Pending]);
        $zulu = Tag::factory()->create(['name' => 'Zulu']);
        $alpha = Tag::factory()->create(['name' => 'Alpha']);
        foreach ([1, 2] as $position) {
            PostItem::factory()->create(['post_id' => $news->id, 'position' => $position])->tags()->attach([$zulu->id, $alpha->id]);
        }
        $private = Post::factory()->create(['date' => '2026-10-29']);
        PostItem::factory()->create(['post_id' => $private->id])->tags()->attach(Tag::factory()->create(['name' => 'Private']));
        Post::factory()->create(['date' => '2026-10-28']);
        $result = app(ArchiveQuery::class)->for($user, 2);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertSame(3, $result->total());
        $this->assertSame(2, $result->perPage());
        $this->assertSame(2, $result->lastPage());
        $rows = array_map(fn ($row) => (array) $row, $result->items());
        $this->assertSame(['2026-10-29', '2026-10-25'], array_column($rows, 'date'));
        $this->assertSame(2, $rows[0]['item_count']);
        $this->assertSame([$alpha->slug, $zulu->slug], collect($rows[0]['tags'])->pluck('slug')->all());
        $this->assertSame(PostStatus::Generated, $rows[0]['news_status']);
        $this->assertSame(PostStatus::Failed, $rows[0]['learning_status']);
        $this->assertSame(0, $rows[1]['item_count']);
        $this->assertSame([], collect($rows[1]['tags'])->all());
        $this->assertNull($rows[1]['news_status']);
        $this->assertSame(PostStatus::Pending, $rows[1]['learning_status']);
        Paginator::currentPageResolver(fn () => 2);
        try {
            $second = app(ArchiveQuery::class)->for($user, 2);
            $this->assertSame(2, $second->currentPage());
            $this->assertCount(1, $second->items());
            $row = (array) $second->items()[0];
            $this->assertSame('2026-10-01', $row['date']);
            $this->assertSame(0, $row['item_count']);
            $this->assertSame(PostStatus::Pending, $row['news_status']);
            $this->assertNull($row['learning_status']);
        } finally {
            Paginator::currentPageResolver(fn () => 1);
        }
    }
}
