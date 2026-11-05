<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LearningKind;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\LearningPost;
use App\Models\Post;
use App\Models\PostItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DayPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00:00', 'UTC'));
        $this->freezeSecond();
    }

    public function test_named_day_route_returns_unwrapped_owned_content_and_distinct_dates_newest_first(): void
    {
        $user = User::factory()->onboarded()->create();
        $news = Post::factory()->for($user)->create(['date' => '2026-10-28', 'status' => PostStatus::Generated]);
        $item = PostItem::factory()->create(['post_id' => $news->id]);
        $learning = Post::factory()->for($user)->create(['date' => '2026-10-28', 'kind' => PostKind::Learning, 'status' => PostStatus::Generated]);
        $tip = LearningPost::query()->create(['post_id' => $learning->id, 'kind' => LearningKind::Tip, 'title' => 'Owned tip', 'body' => 'Owned body', 'links' => []]);
        foreach (['2026-10-20', '2026-10-30'] as $date) {
            Post::factory()->for($user)->create(['date' => $date]);
        }
        $other = User::factory()->create();
        foreach (['2026-10-27', '2026-10-28', '2026-10-29'] as $date) {
            $private = Post::factory()->for($other)->create(['date' => $date, 'status' => PostStatus::Generated]);
            PostItem::factory()->create(['post_id' => $private->id, 'title' => 'Private news']);
            $privateLearning = Post::factory()->for($other)->create(['date' => $date, 'kind' => PostKind::Learning, 'status' => PostStatus::Generated]);
            LearningPost::query()->create(['post_id' => $privateLearning->id, 'kind' => LearningKind::Tip, 'title' => 'Private tip', 'body' => 'Private body', 'links' => []]);
        }

        $this->assertSame(url('/days/2026-10-28'), route('days.show', ['date' => '2026-10-28']));
        $response = $this->actingAs($user)->get(route('days.show', ['date' => '2026-10-28']))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Day', false)
            ->where('day.date', '2026-10-28')
            ->where('day.previous_date', '2026-10-20')
            ->where('day.next_date', '2026-10-30')
            ->where('day.news.status', 'generated')
            ->where('day.news.item_count', 1)
            ->has('day.news.sections', 1)
            ->has('day.news.sections.0.items', 1)
            ->where('day.news.sections.0.items.0.id', $item->id)
            ->where('day.learning.status', 'generated')
            ->where('day.learning.tip.id', $tip->id)
            ->where('day.learning.topic', null)
            ->missing('day.data')
            ->where('available_dates', ['2026-10-30', '2026-10-28', '2026-10-20']));
        $this->assertStringNotContainsString('Private', json_encode($response->inertiaProps(), JSON_THROW_ON_ERROR));
    }

    #[DataProvider('learningKinds')]
    public function test_a_day_with_only_learning_is_available(LearningKind $kind): void
    {
        $user = User::factory()->onboarded()->create();
        $post = Post::factory()->for($user)->create(['date' => '2026-10-28', 'kind' => PostKind::Learning, 'status' => PostStatus::Generated]);
        $entry = LearningPost::query()->create(['post_id' => $post->id, 'kind' => $kind, 'title' => 'Learning', 'body' => 'Body', 'links' => []]);
        $absent = $kind === LearningKind::Tip ? 'topic' : 'tip';

        $this->actingAs($user)->get(route('days.show', ['date' => '2026-10-28']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Day', false)
                ->where('day.date', '2026-10-28')
                ->where('day.previous_date', null)
                ->where('day.next_date', null)
                ->where('day.news', null)
                ->where('day.learning.status', 'generated')
                ->where('day.learning.'.$kind->value.'.id', $entry->id)
                ->where('day.learning.'.$absent, null)
                ->missing('day.data')
                ->where('available_dates', ['2026-10-28']));
    }

    public static function learningKinds(): array
    {
        return ['tip only' => [LearningKind::Tip], 'topic only' => [LearningKind::Topic]];
    }

    public function test_a_valid_date_without_a_post_returns_not_found(): void
    {
        $user = User::factory()->onboarded()->create();
        Post::factory()->for($user)->create(['date' => '2026-10-27']);

        $this->actingAs($user)->get(route('days.show', ['date' => '2026-10-28']))->assertNotFound();
    }

    public function test_another_users_date_returns_not_found(): void
    {
        $user = User::factory()->onboarded()->create();
        Post::factory()->for($user)->create(['date' => '2026-10-27']);
        Post::factory()->create(['date' => '2026-10-28', 'status' => PostStatus::Generated]);

        $this->actingAs($user)->get(route('days.show', ['date' => '2026-10-28']))->assertNotFound();
    }

    #[DataProvider('invalidDates')]
    public function test_dates_must_be_real_and_use_the_exact_format(string $date): void
    {
        $user = User::factory()->onboarded()->create();
        foreach (['2026-01-01', '2026-02-28', '2026-03-01', '2026-04-30', '2026-05-01', '2027-02-09'] as $validDate) {
            Post::factory()->for($user)->create(['date' => $validDate]);
        }

        $this->actingAs($user)->get(route('days.show', ['date' => $date]))->assertNotFound();
    }

    public static function invalidDates(): array
    {
        return [
            'impossible month and day' => ['2026-13-40'],
            'text' => ['abc'],
            'missing zero padding' => ['2026-1-1'],
            'non leap year' => ['2026-02-29'],
            'overflow day' => ['2026-04-31'],
        ];
    }

    #[DataProvider('todayPostStates')]
    public function test_today_redirects_to_dashboard_even_without_a_post(bool $hasPost): void
    {
        $user = User::factory()->onboarded()->create();
        if ($hasPost) {
            Post::factory()->for($user)->create(['date' => '2026-10-30']);
        }

        $this->actingAs($user)->get(route('days.show', ['date' => '2026-10-30']))->assertRedirect(route('dashboard'));
    }

    public static function todayPostStates(): array
    {
        return ['no post' => [false], 'with post' => [true]];
    }

    #[DataProvider('localDates')]
    public function test_today_redirect_uses_local_date_while_the_utc_date_remains_readable(string $instant, string $timezone, string $date): void
    {
        $this->travelTo(CarbonImmutable::parse($instant, 'UTC'));
        $this->freezeSecond();
        $user = User::factory()->onboarded()->create();
        $user->digestSettings->update(['timezone' => $timezone]);
        Post::factory()->for($user)->create(['date' => '2026-10-30']);

        $this->actingAs($user)->get(route('days.show', ['date' => $date]))->assertRedirect(route('dashboard'));
        $this->get(route('days.show', ['date' => '2026-10-30']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Day', false)
                ->where('day.date', '2026-10-30')
                ->where('available_dates', ['2026-10-30']));
    }

    public static function localDates(): array
    {
        return [
            'far east is tomorrow' => ['2026-10-30 23:30:00', 'Pacific/Kiritimati', '2026-10-31'],
            'far west is yesterday' => ['2026-10-30 00:30:00', 'Pacific/Honolulu', '2026-10-29'],
        ];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('days.show', ['date' => '2026-10-28']))->assertRedirect(route('login'));
    }

    public function test_unverified_users_are_redirected_to_verification(): void
    {
        $this->actingAs(User::factory()->onboarded()->unverified()->create())
            ->get(route('days.show', ['date' => '2026-10-28']))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_users_who_have_not_onboarded_are_redirected_to_onboarding(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('days.show', ['date' => '2026-10-28']))
            ->assertRedirect('/onboarding');
    }
}
