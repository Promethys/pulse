<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LearningKind;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\AiKey;
use App\Models\DeliveryChannel;
use App\Models\Interest;
use App\Models\LearningPost;
use App\Models\Post;
use App\Models\PostItem;
use App\Models\SourceSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TodayPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00:00', 'UTC'));
        $this->freezeSecond();
    }

    public function test_no_owned_posts_returns_empty_today_props_even_when_another_user_is_fully_set_up(): void
    {
        $user = User::factory()->onboarded()->create();
        $other = User::factory()->onboarded()->create();
        Post::factory()->for($other)->create(['date' => '2026-10-30', 'status' => PostStatus::Generated]);
        $other->interests()->attach(Interest::factory()->create());
        SourceSubscription::factory()->for($other)->create();
        AiKey::factory()->for($other)->create();
        DeliveryChannel::factory()->for($other)->email()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Today', false)
                ->where('day', null)
                ->where('is_today', false)
                ->where('today', '2026-10-30')
                ->where('next_digest', ['time' => '09:00', 'timezone' => 'UTC'])
                ->where('available_dates', [])
                ->where('setup', ['interests' => false, 'sources' => false, 'ai_key' => false, 'delivery' => false]));
    }

    #[DataProvider('newsStates')]
    public function test_todays_news_controls_the_day_and_next_digest(PostStatus $status, bool $enabled): void
    {
        $user = User::factory()->onboarded()->create();
        $user->digestSettings->update(['news_enabled' => $enabled, 'news_time' => '07:45']);
        Post::factory()->for($user)->create(['date' => '2026-10-30', 'status' => $status]);
        Post::factory()->for($user)->create(['date' => '2026-10-28']);
        Post::factory()->create(['date' => '2026-10-29']);
        $next = $status === PostStatus::Generated || ! $enabled ? null : ['time' => '07:45', 'timezone' => 'UTC'];

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Today', false)
                ->where('day', [
                    'date' => '2026-10-30',
                    'previous_date' => '2026-10-28',
                    'next_date' => null,
                    'news' => ['status' => $status->value, 'item_count' => 0, 'sections' => []],
                    'learning' => null,
                ])
                ->missing('day.data')
                ->where('is_today', true)
                ->where('today', '2026-10-30')
                ->where('next_digest', $next)
                ->where('available_dates', ['2026-10-30', '2026-10-28'])
                ->where('setup', ['interests' => false, 'sources' => false, 'ai_key' => false, 'delivery' => false]));
    }

    public static function newsStates(): array
    {
        return [
            'generated quiet day' => [PostStatus::Generated, true],
            'pending news' => [PostStatus::Pending, true],
            'failed news' => [PostStatus::Failed, true],
            'disabled with pending news' => [PostStatus::Pending, false],
        ];
    }

    public function test_generated_news_and_learning_include_only_the_users_content(): void
    {
        $user = User::factory()->onboarded()->create();
        $news = Post::factory()->for($user)->create(['date' => '2026-10-30', 'status' => PostStatus::Generated]);
        $item = PostItem::factory()->create(['post_id' => $news->id]);
        $learning = Post::factory()->for($user)->create(['date' => '2026-10-30', 'kind' => PostKind::Learning, 'status' => PostStatus::Generated]);
        $tip = LearningPost::query()->create(['post_id' => $learning->id, 'kind' => LearningKind::Tip, 'title' => 'Owned tip', 'body' => 'Owned body', 'links' => []]);
        $private = Post::factory()->create(['date' => '2026-10-30', 'status' => PostStatus::Generated]);
        PostItem::factory()->create(['post_id' => $private->id, 'title' => 'Private news']);
        $privateLearning = Post::factory()->create(['user_id' => $private->user_id, 'date' => '2026-10-30', 'kind' => PostKind::Learning, 'status' => PostStatus::Generated]);
        LearningPost::query()->create(['post_id' => $privateLearning->id, 'kind' => LearningKind::Tip, 'title' => 'Private tip', 'body' => 'Private body', 'links' => []]);

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Today', false)
            ->where('day.date', '2026-10-30')
            ->where('day.previous_date', null)
            ->where('day.next_date', null)
            ->where('day.news.status', 'generated')
            ->where('day.news.item_count', 1)
            ->has('day.news.sections', 1)
            ->has('day.news.sections.0.items', 1)
            ->where('day.news.sections.0.items.0.id', $item->id)
            ->where('day.learning.status', 'generated')
            ->where('day.learning.tip.id', $tip->id)
            ->where('day.learning.topic', null)
            ->missing('day.data')
            ->where('is_today', true)
            ->where('today', '2026-10-30')
            ->where('next_digest', null)
            ->where('available_dates', ['2026-10-30'])
            ->where('setup', ['interests' => false, 'sources' => false, 'ai_key' => false, 'delivery' => false]));
        $this->assertStringNotContainsString('Private', json_encode($response->inertiaProps(), JSON_THROW_ON_ERROR));
    }

    #[DataProvider('newsEnabledStates')]
    public function test_latest_owned_day_is_used_until_today_has_a_post(bool $enabled): void
    {
        $user = User::factory()->onboarded()->create();
        $user->digestSettings->update(['news_enabled' => $enabled]);
        Post::factory()->for($user)->create(['date' => '2026-10-20']);
        Post::factory()->for($user)->create(['date' => '2026-10-28', 'status' => PostStatus::Generated]);
        Post::factory()->for($user)->create(['date' => '2026-10-28', 'kind' => PostKind::Learning, 'status' => PostStatus::Pending]);
        Post::factory()->create(['date' => '2026-10-30', 'status' => PostStatus::Generated]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Today', false)
                ->where('day', [
                    'date' => '2026-10-28',
                    'previous_date' => '2026-10-20',
                    'next_date' => null,
                    'news' => ['status' => 'generated', 'item_count' => 0, 'sections' => []],
                    'learning' => ['status' => 'pending', 'tip' => null, 'topic' => null],
                ])
                ->missing('day.data')
                ->where('is_today', false)
                ->where('today', '2026-10-30')
                ->where('next_digest', $enabled ? ['time' => '09:00', 'timezone' => 'UTC'] : null)
                ->where('available_dates', ['2026-10-28', '2026-10-20'])
                ->where('setup', ['interests' => false, 'sources' => false, 'ai_key' => false, 'delivery' => false]));
    }

    #[DataProvider('newsEnabledStates')]
    public function test_no_news_post_keeps_next_digest_only_when_news_is_enabled(bool $enabled): void
    {
        $user = User::factory()->onboarded()->create();
        $user->digestSettings->update(['news_enabled' => $enabled]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Today', false)
                ->where('day', null)
                ->where('is_today', false)
                ->where('today', '2026-10-30')
                ->where('next_digest', $enabled ? ['time' => '09:00', 'timezone' => 'UTC'] : null)
                ->where('available_dates', [])
                ->where('setup', ['interests' => false, 'sources' => false, 'ai_key' => false, 'delivery' => false]));
    }

    public static function newsEnabledStates(): array
    {
        return ['enabled' => [true], 'disabled' => [false]];
    }

    #[DataProvider('learningStates')]
    public function test_todays_learning_post_takes_precedence_over_older_news_without_suppressing_next_news(PostStatus $status): void
    {
        $user = User::factory()->onboarded()->create();
        Post::factory()->for($user)->create(['date' => '2026-10-29', 'status' => PostStatus::Generated]);
        Post::factory()->for($user)->create(['date' => '2026-10-30', 'kind' => PostKind::Learning, 'status' => $status]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Today', false)
                ->where('day', [
                    'date' => '2026-10-30',
                    'previous_date' => '2026-10-29',
                    'next_date' => null,
                    'news' => null,
                    'learning' => ['status' => $status->value, 'tip' => null, 'topic' => null],
                ])
                ->missing('day.data')
                ->where('is_today', true)
                ->where('today', '2026-10-30')
                ->where('next_digest', ['time' => '09:00', 'timezone' => 'UTC'])
                ->where('available_dates', ['2026-10-30', '2026-10-29'])
                ->where('setup', ['interests' => false, 'sources' => false, 'ai_key' => false, 'delivery' => false]));
    }

    public static function learningStates(): array
    {
        return [
            'generated' => [PostStatus::Generated],
            'pending' => [PostStatus::Pending],
            'failed' => [PostStatus::Failed],
        ];
    }

    public function test_latest_learning_only_day_is_used_when_there_is_no_post_today(): void
    {
        $user = User::factory()->onboarded()->create();
        Post::factory()->for($user)->create(['date' => '2026-10-20', 'status' => PostStatus::Generated]);
        Post::factory()->for($user)->create(['date' => '2026-10-29', 'kind' => PostKind::Learning, 'status' => PostStatus::Pending]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Today', false)
                ->where('day', [
                    'date' => '2026-10-29',
                    'previous_date' => '2026-10-20',
                    'next_date' => null,
                    'news' => null,
                    'learning' => ['status' => 'pending', 'tip' => null, 'topic' => null],
                ])
                ->missing('day.data')
                ->where('is_today', false)
                ->where('today', '2026-10-30')
                ->where('next_digest', ['time' => '09:00', 'timezone' => 'UTC'])
                ->where('available_dates', ['2026-10-29', '2026-10-20'])
                ->where('setup', ['interests' => false, 'sources' => false, 'ai_key' => false, 'delivery' => false]));
    }

    #[DataProvider('localDates')]
    public function test_today_and_next_digest_use_the_users_local_date(string $instant, string $timezone, string $date, string $utcDate): void
    {
        $this->travelTo(CarbonImmutable::parse($instant, 'UTC'));
        $this->freezeSecond();
        $user = User::factory()->onboarded()->create();
        $user->digestSettings->update(['timezone' => $timezone, 'news_time' => '06:15']);
        Post::factory()->for($user)->create(['date' => $date, 'status' => PostStatus::Pending]);
        Post::factory()->for($user)->create(['date' => $utcDate, 'status' => PostStatus::Generated]);
        $dates = [$date, $utcDate];
        rsort($dates);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Today', false)
                ->where('day.date', $date)
                ->where('day.news.status', 'pending')
                ->where('day.news.item_count', 0)
                ->where('day.news.sections', [])
                ->where('day.learning', null)
                ->missing('day.data')
                ->where('is_today', true)
                ->where('today', $date)
                ->where('next_digest', ['time' => '06:15', 'timezone' => $timezone])
                ->where('available_dates', $dates)
                ->where('setup', ['interests' => false, 'sources' => false, 'ai_key' => false, 'delivery' => false]));
    }

    public static function localDates(): array
    {
        return [
            'far east is tomorrow' => ['2026-10-30 23:30:00', 'Pacific/Kiritimati', '2026-10-31', '2026-10-30'],
            'far west is yesterday' => ['2026-10-30 00:30:00', 'Pacific/Honolulu', '2026-10-29', '2026-10-30'],
        ];
    }

    #[DataProvider('setupStates')]
    public function test_setup_flags_reflect_only_the_users_qualifying_configuration(string $state, array $expected): void
    {
        $user = User::factory()->onboarded()->create(['uses_instance_ai' => $state === 'instance ai', 'is_admin' => true]);
        if (in_array($state, ['interests', 'complete'], true)) {
            $user->interests()->attach(Interest::factory()->create());
        }
        if (in_array($state, ['sources', 'disabled source', 'complete'], true)) {
            SourceSubscription::factory()->for($user)->create(['enabled' => $state !== 'disabled source']);
        }
        if (in_array($state, ['ai key', 'complete'], true)) {
            AiKey::factory()->for($user)->create();
        }
        if (in_array($state, ['email', 'disabled email', 'complete'], true)) {
            DeliveryChannel::factory()->for($user)->email()->create(['enabled' => $state !== 'disabled email']);
        }
        if ($state === 'discord bot') {
            DeliveryChannel::factory()->for($user)->discordBot()->create();
        }
        if ($state === 'discord webhook') {
            DeliveryChannel::factory()->for($user)->discordWebhook()->create();
        }
        if (in_array($state, ['in app', 'disabled email'], true)) {
            DeliveryChannel::factory()->for($user)->inApp()->create();
        }

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Today', false)
            ->where('setup', array_combine(['interests', 'sources', 'ai_key', 'delivery'], $expected))
            ->missing('uses_instance_ai')
            ->missing('is_admin')
            ->missing('setup.uses_instance_ai')
            ->missing('setup.is_admin')
            ->missing('auth.user.uses_instance_ai')
            ->missing('auth.user.is_admin'));
        $serialized = json_encode($response->inertiaProps(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('"uses_instance_ai"', $serialized);
        $this->assertStringNotContainsString('"is_admin"', $serialized);
    }

    public static function setupStates(): array
    {
        return [
            'none' => ['none', [false, false, false, false]],
            'interest alone' => ['interests', [true, false, false, false]],
            'enabled subscription alone' => ['sources', [false, true, false, false]],
            'disabled subscription' => ['disabled source', [false, false, false, false]],
            'own ai key' => ['ai key', [false, false, true, false]],
            'instance ai without own key' => ['instance ai', [false, false, true, false]],
            'enabled email' => ['email', [false, false, false, true]],
            'enabled discord bot' => ['discord bot', [false, false, false, true]],
            'enabled discord webhook' => ['discord webhook', [false, false, false, true]],
            'in app only' => ['in app', [false, false, false, false]],
            'disabled email and enabled in app' => ['disabled email', [false, false, false, false]],
            'complete' => ['complete', [true, true, true, true]],
        ];
    }
}
