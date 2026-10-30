<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostItem;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TagPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00:00', 'UTC'));
        $this->freezeSecond();
    }

    public function test_named_route_returns_tag_counts_weeks_and_newest_items_with_unwrapped_props(): void
    {
        $user = User::factory()->onboarded()->create();
        $tag = Tag::factory()->create(['name' => 'Laravel']);
        $this->taggedItem($user, $tag, '2026-10-20');
        $newer = $this->taggedItem($user, $tag, '2026-10-29');

        $this->assertSame(url('/tags/'.$tag->slug), route('tags.show', ['tag' => $tag->slug]));
        $this->actingAs($user)->get(route('tags.show', ['tag' => $tag->slug]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Tag', false)
                ->where('tag', ['id' => $tag->id, 'slug' => $tag->slug, 'name' => 'Laravel'])
                ->where('count', 2)
                ->where('weeks', [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1])
                ->has('items', 2)
                ->where('items.0.id', $newer->id)
                ->where('items.0.date', '2026-10-29')
                ->where('pagination', ['current_page' => 1, 'last_page' => 1, 'total' => 2, 'prev_url' => null, 'next_url' => null]));
    }

    public function test_unknown_slug_returns_not_found(): void
    {
        $this->actingAs(User::factory()->onboarded()->create())
            ->get(route('tags.show', ['tag' => 'no-such-tag']))->assertNotFound();
    }

    public function test_existing_tag_with_only_other_users_items_is_empty(): void
    {
        $tag = Tag::factory()->create();
        $this->taggedItem(User::factory()->create(), $tag, '2026-10-29');

        $this->actingAs(User::factory()->onboarded()->create())
            ->get(route('tags.show', ['tag' => $tag->slug]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Tag', false)
                ->where('count', 0)
                ->where('weeks', array_fill(0, 12, 0))
                ->has('items', 0));
    }

    public function test_other_users_items_and_counts_are_excluded(): void
    {
        $user = User::factory()->onboarded()->create();
        $tag = Tag::factory()->create();
        $owned = $this->taggedItem($user, $tag, '2026-10-29');
        $other = User::factory()->create();
        foreach (['2026-10-28', '2026-10-29', '2026-10-30'] as $date) {
            $this->taggedItem($other, $tag, $date);
        }

        $this->actingAs($user)->get(route('tags.show', ['tag' => $tag->slug]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Tag', false)
                ->where('count', 1)
                ->where('weeks', [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1])
                ->has('items', 1)
                ->where('items.0.id', $owned->id));
    }

    #[DataProvider('tagPages')]
    public function test_thirty_items_per_page_and_pagination_boundaries(int $total, int $current, int $count, int $last, ?int $previous, ?int $next): void
    {
        $user = User::factory()->onboarded()->create();
        $tag = Tag::factory()->create();
        $ids = [];
        for ($offset = 0; $offset < $total; $offset++) {
            $ids[] = $this->taggedItem($user, $tag, CarbonImmutable::parse('2026-10-30')->subDays($offset)->toDateString())->id;
        }

        $response = $this->actingAs($user)->get(route('tags.show', ['tag' => $tag->slug, 'page' => $current]))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Tag', false)
            ->has('items', $count)
            ->where('count', $total)
            ->where('pagination', [
                'current_page' => $current,
                'last_page' => $last,
                'total' => $total,
                'prev_url' => $previous === null ? null : route('tags.show', ['tag' => $tag->slug, 'page' => $previous]),
                'next_url' => $next === null ? null : route('tags.show', ['tag' => $tag->slug, 'page' => $next]),
            ]));
        $this->assertSame(array_slice($ids, ($current - 1) * 30, 30), array_column($response->inertiaProps('items'), 'id'));
    }

    public static function tagPages(): array
    {
        return [
            'one item' => [1, 1, 1, 1, null, null],
            'exactly thirty' => [30, 1, 30, 1, null, null],
            'first of two' => [31, 1, 30, 2, null, 2],
            'last of two' => [31, 2, 1, 2, 1, null],
        ];
    }

    #[DataProvider('tagSizes')]
    public function test_page_beyond_last_redirects_to_last_page(int $total, int $last): void
    {
        $user = User::factory()->onboarded()->create();
        $tag = Tag::factory()->create();
        for ($offset = 0; $offset < $total; $offset++) {
            $this->taggedItem($user, $tag, CarbonImmutable::parse('2026-10-30')->subDays($offset)->toDateString());
        }

        $this->actingAs($user)->get(route('tags.show', ['tag' => $tag->slug, 'page' => 99]))
            ->assertRedirect(route('tags.show', ['tag' => $tag->slug, 'page' => $last]));
    }

    public static function tagSizes(): array
    {
        return [
            'empty' => [0, 1],
            'exactly thirty' => [30, 1],
            'second page' => [31, 2],
        ];
    }

    public function test_weeks_use_tomorrow_in_the_users_timezone_and_exclude_eighty_four_days_ago(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-30 23:30:00', 'UTC'));
        $this->freezeSecond();
        $user = User::factory()->onboarded()->create();
        $user->digestSettings->update(['timezone' => 'Pacific/Kiritimati']);
        $tag = Tag::factory()->create();
        $this->taggedItem($user, $tag, '2026-10-31');
        $this->taggedItem($user, $tag, '2026-08-08');

        $this->actingAs($user)->get(route('tags.show', ['tag' => $tag->slug]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Tag', false)
                ->where('count', 2)
                ->where('weeks', [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1]));
    }

    public function test_weeks_use_yesterday_in_the_users_timezone_and_exclude_future_and_old_dates(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-30 00:30:00', 'UTC'));
        $this->freezeSecond();
        $user = User::factory()->onboarded()->create();
        $user->digestSettings->update(['timezone' => 'Pacific/Honolulu']);
        $tag = Tag::factory()->create();
        foreach (['2026-10-30', '2026-10-29', '2026-08-06'] as $date) {
            $this->taggedItem($user, $tag, $date);
        }

        $this->actingAs($user)->get(route('tags.show', ['tag' => $tag->slug]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Tag', false)
                ->where('count', 3)
                ->where('weeks', [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1]));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $tag = Tag::factory()->create();

        $this->get(route('tags.show', ['tag' => $tag->slug]))->assertRedirect(route('login'));
    }

    public function test_unverified_users_are_redirected_to_verification(): void
    {
        $tag = Tag::factory()->create();

        $this->actingAs(User::factory()->onboarded()->unverified()->create())
            ->get(route('tags.show', ['tag' => $tag->slug]))->assertRedirect(route('verification.notice'));
    }

    public function test_users_who_have_not_onboarded_are_redirected_to_onboarding(): void
    {
        $tag = Tag::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('tags.show', ['tag' => $tag->slug]))->assertRedirect('/onboarding');
    }

    private function taggedItem(User $user, Tag $tag, string $date): PostItem
    {
        $post = Post::factory()->for($user)->create(['date' => $date, 'status' => PostStatus::Generated]);
        $item = PostItem::factory()->create(['post_id' => $post->id]);
        $item->tags()->attach($tag);

        return $item;
    }
}
