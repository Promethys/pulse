<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PostKind;
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

class ArchivePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00:00', 'UTC'));
        $this->freezeSecond();
    }

    public function test_named_route_returns_distinct_owned_days_newest_first_with_unwrapped_props(): void
    {
        $user = User::factory()->onboarded()->create();
        $news = Post::factory()->for($user)->create(['date' => '2026-10-29', 'status' => PostStatus::Generated]);
        Post::factory()->for($user)->create(['date' => '2026-10-29', 'kind' => PostKind::Learning, 'status' => PostStatus::Failed]);
        Post::factory()->for($user)->create(['date' => '2026-10-20', 'kind' => PostKind::Learning, 'status' => PostStatus::Pending]);
        $zulu = Tag::factory()->create(['name' => 'Zulu']);
        $alpha = Tag::factory()->create(['name' => 'Alpha']);
        foreach ([1, 2] as $position) {
            PostItem::factory()->create(['post_id' => $news->id, 'position' => $position])->tags()->attach([$zulu->id, $alpha->id]);
        }
        $other = User::factory()->create();
        foreach (['2026-10-30', '2026-10-29', '2026-10-19'] as $date) {
            $private = Post::factory()->for($other)->create(['date' => $date]);
            PostItem::factory()->create(['post_id' => $private->id])->tags()->attach(Tag::factory()->create(['name' => 'Private '.$date]));
        }

        $this->assertSame(url('/archive'), route('archive.index'));
        $response = $this->actingAs($user)->get(route('archive.index'))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Archive', false)
            ->where('days', [
                [
                    'date' => '2026-10-29',
                    'item_count' => 2,
                    'tags' => [
                        ['id' => $alpha->id, 'slug' => $alpha->slug, 'name' => 'Alpha'],
                        ['id' => $zulu->id, 'slug' => $zulu->slug, 'name' => 'Zulu'],
                    ],
                    'news_status' => 'generated',
                    'learning_status' => 'failed',
                ],
                ['date' => '2026-10-20', 'item_count' => 0, 'tags' => [], 'news_status' => null, 'learning_status' => 'pending'],
            ])
            ->where('pagination', ['current_page' => 1, 'last_page' => 1, 'total' => 2, 'prev_url' => null, 'next_url' => null])
            ->where('today', '2026-10-30')
            ->missing('days.data'));
        $this->assertStringNotContainsString('Private', json_encode($response->inertiaProps(), JSON_THROW_ON_ERROR));
    }

    public function test_empty_archive_does_not_include_other_users_days(): void
    {
        Post::factory()->create(['date' => '2026-10-29']);

        $this->actingAs(User::factory()->onboarded()->create())->get(route('archive.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Archive', false)
                ->where('days', [])
                ->where('pagination', ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'prev_url' => null, 'next_url' => null])
                ->where('today', '2026-10-30'));
    }

    #[DataProvider('postStatuses')]
    public function test_news_statuses_are_serialized_with_empty_counts_and_absent_learning(PostStatus $status): void
    {
        $user = User::factory()->onboarded()->create();
        Post::factory()->for($user)->create(['date' => '2026-10-29', 'status' => $status]);

        $this->actingAs($user)->get(route('archive.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Archive', false)
                ->where('days', [['date' => '2026-10-29', 'item_count' => 0, 'tags' => [], 'news_status' => $status->value, 'learning_status' => null]]));
    }

    #[DataProvider('postStatuses')]
    public function test_learning_only_statuses_are_serialized_without_inventing_news(PostStatus $status): void
    {
        $user = User::factory()->onboarded()->create();
        Post::factory()->for($user)->create(['date' => '2026-10-29', 'kind' => PostKind::Learning, 'status' => $status]);

        $this->actingAs($user)->get(route('archive.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Archive', false)
                ->where('days', [['date' => '2026-10-29', 'item_count' => 0, 'tags' => [], 'news_status' => null, 'learning_status' => $status->value]]));
    }

    public static function postStatuses(): array
    {
        return ['generated' => [PostStatus::Generated], 'pending' => [PostStatus::Pending], 'failed' => [PostStatus::Failed]];
    }

    #[DataProvider('archivePages')]
    public function test_thirty_distinct_days_per_page_and_pagination_boundaries(int $current, int $count, ?int $previous, ?int $next): void
    {
        $user = User::factory()->onboarded()->create();
        for ($offset = 0; $offset < 61; $offset++) {
            $date = CarbonImmutable::parse('2026-10-30')->subDays($offset)->toDateString();
            Post::factory()->for($user)->create(['date' => $date]);
            Post::factory()->for($user)->create(['date' => $date, 'kind' => PostKind::Learning]);
        }
        Post::factory()->create(['date' => '2026-10-31']);
        $expectedDates = array_map(fn (int $offset) => CarbonImmutable::parse('2026-10-30')->subDays($offset)->toDateString(), range(($current - 1) * 30, ($current - 1) * 30 + $count - 1));

        $response = $this->actingAs($user)->get(route('archive.index', ['page' => $current]))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Archive', false)
            ->has('days', $count)
            ->where('pagination', [
                'current_page' => $current,
                'last_page' => 3,
                'total' => 61,
                'prev_url' => $previous === null ? null : route('archive.index', ['page' => $previous]),
                'next_url' => $next === null ? null : route('archive.index', ['page' => $next]),
            ]));
        $this->assertSame($expectedDates, array_column($response->inertiaProps('days'), 'date'));
    }

    public static function archivePages(): array
    {
        return ['first' => [1, 30, null, 2], 'middle' => [2, 30, 1, 3], 'last' => [3, 1, 2, null]];
    }

    #[DataProvider('archiveSizes')]
    public function test_page_beyond_last_redirects_to_last_page(int $days, int $last): void
    {
        $user = User::factory()->onboarded()->create();
        for ($offset = 0; $offset < $days; $offset++) {
            Post::factory()->for($user)->create(['date' => CarbonImmutable::parse('2026-10-30')->subDays($offset)->toDateString()]);
        }

        $this->actingAs($user)->get(route('archive.index', ['page' => 99]))
            ->assertRedirect(route('archive.index', ['page' => $last]));
    }

    public static function archiveSizes(): array
    {
        return ['empty' => [0, 1], 'exactly thirty' => [30, 1], 'second page' => [31, 2]];
    }

    #[DataProvider('localDates')]
    public function test_today_uses_the_users_timezone(string $instant, string $timezone, string $today): void
    {
        $this->travelTo(CarbonImmutable::parse($instant, 'UTC'));
        $this->freezeSecond();
        $user = User::factory()->onboarded()->create();
        $user->digestSettings->update(['timezone' => $timezone]);

        $this->actingAs($user)->get(route('archive.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Archive', false)->where('today', $today));
    }

    public static function localDates(): array
    {
        return [
            'tomorrow' => ['2026-10-30 23:30:00', 'Pacific/Kiritimati', '2026-10-31'],
            'yesterday' => ['2026-10-30 00:30:00', 'Pacific/Honolulu', '2026-10-29'],
        ];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('archive.index'))->assertRedirect(route('login'));
    }

    public function test_unverified_users_are_redirected_to_verification(): void
    {
        $this->actingAs(User::factory()->onboarded()->unverified()->create())
            ->get(route('archive.index'))->assertRedirect(route('verification.notice'));
    }

    public function test_users_who_have_not_onboarded_are_redirected_to_onboarding(): void
    {
        $this->actingAs(User::factory()->create())->get(route('archive.index'))->assertRedirect('/onboarding');
    }
}
