<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeliveryChannelType;
use App\Enums\DeliveryStatus;
use App\Enums\LearningKind;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Enums\SourceType;
use App\Models\DeliveryChannel;
use App\Models\Item;
use App\Models\LearningTopicUsage;
use App\Models\Post;
use App\Models\PostItem;
use App\Models\Source;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ShowcaseDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShowcaseDataSeederTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = [
        'users', 'digest_settings', 'interests', 'interest_user', 'sources',
        'interest_source', 'source_subscriptions', 'tags', 'items', 'item_source',
        'posts', 'post_items', 'post_item_tag', 'learning_topics',
        'learning_posts', 'learning_post_tag', 'learning_topic_usages',
        'delivery_channels', 'deliveries',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-30 11:55:00', 'Europe/Paris'));
        $this->freezeSecond();
    }

    #[DataProvider('digestInstants')]
    public function test_latest_dates_and_ninety_day_history_follow_each_local_cutoff(string $instant, string $timezone, string $newsDate, string $learningDate): void
    {
        $this->travelTo(CarbonImmutable::parse($instant, $timezone));

        $this->seed(ShowcaseDataSeeder::class);

        $user = $this->showcaseUser();
        foreach ([[PostKind::News, $newsDate, 11], [PostKind::Learning, $learningDate, 8]] as [$kind, $latest, $missingOffset]) {
            $expected = [];
            for ($offset = 0; $offset < 90; $offset++) {
                if ($offset !== $missingOffset) {
                    $expected[] = CarbonImmutable::parse($latest, 'Europe/Paris')->subDays($offset)->toDateString();
                }
            }

            $dates = $user->posts()->where('kind', $kind)->orderByDesc('date')->get()
                ->map(fn (Post $post): string => $post->date->toDateString())->all();
            $this->assertSame($expected, $dates);
        }
    }

    public static function digestInstants(): array
    {
        return [
            'after both digests' => ['2026-10-30 11:55:00', 'Europe/Paris', '2026-10-30', '2026-10-30'],
            'before both digests' => ['2026-10-30 02:00:00', 'Europe/Paris', '2026-10-29', '2026-10-29'],
            'between digests' => ['2026-10-30 09:15:00', 'Europe/Paris', '2026-10-30', '2026-10-29'],
            'just before news' => ['2026-10-30 08:59:59', 'Europe/Paris', '2026-10-29', '2026-10-29'],
            'exactly news time' => ['2026-10-30 09:00:00', 'Europe/Paris', '2026-10-30', '2026-10-29'],
            'just before learning' => ['2026-10-30 09:29:59', 'Europe/Paris', '2026-10-30', '2026-10-29'],
            'exactly learning time' => ['2026-10-30 09:30:00', 'Europe/Paris', '2026-10-30', '2026-10-30'],
            'Paris is tomorrow in winter' => ['2026-10-30 23:30:00', 'UTC', '2026-10-30', '2026-10-30'],
            'Paris is tomorrow in summer' => ['2026-07-15 22:30:00', 'UTC', '2026-07-15', '2026-07-15'],
        ];
    }

    #[DataProvider('allowedEnvironments')]
    public function test_creates_the_dedicated_account_settings_catalog_subscriptions_and_channels(string $environment): void
    {
        $this->app->detectEnvironment(fn (): string => $environment);

        $this->seed(ShowcaseDataSeeder::class);

        $user = $this->showcaseUser();
        $this->assertSame(1, User::query()->count());
        $this->assertSame('Showcase', $user->name);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->uses_instance_ai);
        $settings = $user->digestSettings;
        $this->assertNotNull($settings);
        $this->assertNotNull($settings->onboarded_at);
        $this->assertSame('Europe/Paris', $settings->timezone);
        $this->assertSame('09:00', substr($settings->news_time, 0, 5));
        $this->assertSame('09:30', substr($settings->learning_time, 0, 5));
        $this->assertTrue($settings->news_enabled);
        $this->assertTrue($settings->learning_enabled);
        $this->assertSame(['Artificial Intelligence', 'DevOps', 'Programming'], $user->interests()->orderBy('name')->pluck('name')->all());
        $this->assertSame(3, $user->interests()->where('has_learning', true)->count());
        $this->assertGreaterThan(0, DB::table('tags')->count());
        $this->assertGreaterThan(0, DB::table('learning_topics')->count());

        $defaultIds = Source::query()->whereNull('user_id')->where('is_default', true)
            ->whereHas('interests', fn ($query) => $query->whereIn('interests.id', $user->interests()->pluck('interests.id')))
            ->orderBy('id')->pluck('id')->all();
        $this->assertNotEmpty($defaultIds);
        $this->assertSame($defaultIds, $user->sourceSubscriptions()->whereIn('source_id', $defaultIds)->orderBy('source_id')->pluck('source_id')->all());
        $this->assertSame(1, $user->sourceSubscriptions()->where('enabled', false)->count());
        $this->assertGreaterThan(0, $user->sourceSubscriptions()->where('enabled', true)->count());
        $this->assertSame(1, $user->customSources()->count());
        $custom = $user->customSources()->sole();
        $this->assertSame(SourceType::Feed->value, $custom->type->value);
        $this->assertNotEmpty($custom->last_error);
        $this->assertDatabaseHas('source_subscriptions', ['user_id' => $user->id, 'source_id' => $custom->id]);

        $this->assertEqualsCanonicalizing(['discord_webhook', 'email', 'in_app'], $user->deliveryChannels()->pluck('type')->map(fn ($type): string => $type->value)->all());
        $this->assertSame(3, $user->deliveryChannels()->where('enabled', true)->count());
        $this->assertSame(['a1b2', 'c3d4'], $user->aiKeys()->orderBy('position')->pluck('key_suffix')->all());
        $email = $user->deliveryChannels()->where('type', DeliveryChannelType::Email)->sole();
        $this->assertSame('showcase@example.com', $email->settings['address']);
    }

    public static function allowedEnvironments(): array
    {
        return ['testing' => ['testing'], 'local' => ['local']];
    }

    public function test_rerunning_preserves_every_table_count_and_deterministic_content(): void
    {
        $this->seed(ShowcaseDataSeeder::class);
        $counts = $this->tableCounts();
        $content = $this->contentSnapshot();
        $userId = $this->showcaseUser()->id;
        $items = Item::query()->orderBy('url_normalized')->pluck('id', 'url_normalized')->all();

        $this->seed(ShowcaseDataSeeder::class);

        $this->assertSame($counts, $this->tableCounts());
        $this->assertSame($content, $this->contentSnapshot());
        $this->assertSame($userId, $this->showcaseUser()->id);
        $this->assertSame($items, Item::query()->orderBy('url_normalized')->pluck('id', 'url_normalized')->all());
    }

    public function test_rerunning_two_days_later_moves_history_without_growing_any_table(): void
    {
        $this->seed(ShowcaseDataSeeder::class);
        $counts = $this->tableCounts();

        $this->travelTo(CarbonImmutable::parse('2026-11-01 11:55:00', 'Europe/Paris'));
        $this->seed(ShowcaseDataSeeder::class);

        $this->assertSame($counts, $this->tableCounts());
        foreach ([PostKind::News, PostKind::Learning] as $kind) {
            $posts = $this->showcaseUser()->posts()->where('kind', $kind);
            $this->assertSame('2026-11-01', (clone $posts)->latest('date')->firstOrFail()->date->toDateString());
            $this->assertSame('2026-08-04', (clone $posts)->oldest('date')->firstOrFail()->date->toDateString());
            $this->assertSame(89, $posts->count());
        }
        $this->assertSame(0, $this->showcaseUser()->learningTopicUsages()->whereDate('used_on', '<', '2026-08-04')->count());
    }

    public function test_rerunning_leaves_another_users_content_and_configuration_untouched(): void
    {
        $other = User::factory()->onboarded()->create();
        $post = Post::factory()->for($other)->create(['date' => '2026-10-30', 'status' => PostStatus::Generated]);
        $selection = PostItem::factory()->create(['post_id' => $post->id, 'title' => 'Private news', 'summary' => 'Private summary']);
        $channel = DeliveryChannel::factory()->for($other)->email()->create();
        $usage = LearningTopicUsage::factory()->for($other)->create(['used_on' => '2026-10-30']);
        $before = [
            'user' => DB::table('users')->where('id', $other->id)->first(),
            'settings' => DB::table('digest_settings')->where('user_id', $other->id)->first(),
            'post' => DB::table('posts')->where('id', $post->id)->first(),
            'selection' => DB::table('post_items')->where('id', $selection->id)->first(),
            'item' => DB::table('items')->where('id', $selection->item_id)->first(),
            'channel' => DB::table('delivery_channels')->where('id', $channel->id)->first(),
            'usage' => DB::table('learning_topic_usages')->where('id', $usage->id)->first(),
        ];

        $this->seed(ShowcaseDataSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-01 11:55:00', 'Europe/Paris'));
        $this->seed(ShowcaseDataSeeder::class);

        foreach ($before as $key => $row) {
            $table = match ($key) {
                'user' => 'users', 'settings' => 'digest_settings', 'post' => 'posts',
                'selection' => 'post_items', 'item' => 'items',
                'channel' => 'delivery_channels', 'usage' => 'learning_topic_usages',
            };
            $this->assertNotNull($row);
            foreach ((array) $row as $column => $value) {
                $this->assertSame($value, DB::table($table)->where('id', $row->id)->value($column), $table.'.'.$column);
            }
        }
        $this->assertSame(1, $other->posts()->count());
    }

    public function test_production_warns_and_writes_no_rows(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $counts = $this->tableCounts();
        $command = Mockery::mock(Command::class);
        $command->shouldReceive('warn')->once()->withArgs(fn (string $message): bool => trim($message) !== '');

        $seeder = $this->app->make(ShowcaseDataSeeder::class);
        $seeder->setCommand($command);
        $seeder->run();

        $this->assertSame($counts, $this->tableCounts());
        $this->assertSame(0, User::query()->count());
    }

    public function test_special_days_have_quiet_failed_and_missing_states_relative_to_each_latest_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-30 09:15:00', 'Europe/Paris'));
        $this->seed(ShowcaseDataSeeder::class);

        $user = $this->showcaseUser();
        $quiet = $user->posts()->where('kind', PostKind::News)->whereDate('date', '2026-10-27')->sole();
        $this->assertSame(PostStatus::Generated->value, $quiet->status->value);
        $this->assertSame(0, $quiet->postItems()->count());
        $this->assertNotEmpty($quiet->rendered);
        $failed = $user->posts()->where('kind', PostKind::News)->whereDate('date', '2026-10-25')->sole();
        $this->assertSame(PostStatus::Failed->value, $failed->status->value);
        $this->assertNotEmpty($failed->last_error);
        $this->assertSame(0, $failed->postItems()->count());
        $this->assertSame(0, $user->posts()->where('kind', PostKind::News)->whereDate('date', '2026-10-19')->count());
        $this->assertSame(0, $user->posts()->where('kind', PostKind::Learning)->whereDate('date', '2026-10-21')->count());
        $this->assertSame(0, $user->learningTopicUsages()->whereDate('used_on', '2026-10-21')->count());
        $this->assertSame(1, $user->posts()->where('kind', PostKind::Learning)->whereDate('date', '2026-10-19')->count());
        $this->assertSame(1, $user->posts()->where('kind', PostKind::News)->whereDate('date', '2026-10-21')->count());
    }

    public function test_news_has_a_reusable_pool_summaries_tags_sources_positions_and_rendered_content(): void
    {
        $this->seed(ShowcaseDataSeeder::class);

        $user = $this->showcaseUser();
        $posts = $user->posts()->where('kind', PostKind::News)->with('postItems.tags', 'postItems.item.sources')->get();
        $selections = $posts->flatMap(fn (Post $post) => $post->postItems);
        $this->assertGreaterThanOrEqual(40, $selections->pluck('item_id')->unique()->count());
        $this->assertGreaterThanOrEqual(40, $selections->pluck('title')->unique()->count());
        $headlines = $selections->whereNull('summary')->count();
        $this->assertGreaterThanOrEqual(0.10, $headlines / $selections->count());
        $this->assertLessThanOrEqual(0.25, $headlines / $selections->count());

        foreach ($posts as $post) {
            if (in_array($post->date->toDateString(), ['2026-10-27', '2026-10-25'], true)) {
                continue;
            }
            $this->assertSame(PostStatus::Generated->value, $post->status->value);
            $this->assertGreaterThanOrEqual(6, $post->postItems->count());
            $this->assertLessThanOrEqual(12, $post->postItems->count());
            $positions = $post->postItems->pluck('position')->all();
            $this->assertSame(count($positions), count(array_unique($positions)));
            $sorted = $positions;
            sort($sorted);
            $this->assertSame($sorted, $positions);
            $this->assertNotEmpty($post->rendered['title']);
            $this->assertNotEmpty($post->rendered['messages']);
            $this->assertIsArray($post->rendered['tag_slugs']);
        }
        foreach ($selections as $selection) {
            $this->assertNotEmpty($selection->title);
            if ($selection->summary !== null) {
                $this->assertNotEmpty($selection->summary);
            }
            $this->assertGreaterThanOrEqual(1, $selection->tags->count());
            $this->assertLessThanOrEqual(3, $selection->tags->count());
            $this->assertStringStartsWith('https://example.com/showcase/', $selection->item->url_normalized);
            $this->assertGreaterThanOrEqual(1, $selection->item->sources->count());
            $this->assertLessThanOrEqual(2, $selection->item->sources->count());
            foreach ($selection->item->sources as $source) {
                $this->assertTrue($source->user_id === null || $source->user_id === $user->id);
            }
        }
    }

    public function test_learning_has_daily_tips_topics_markdown_tags_links_and_owned_topic_usage(): void
    {
        $this->seed(ShowcaseDataSeeder::class);

        $user = $this->showcaseUser();
        $posts = $user->posts()->where('kind', PostKind::Learning)->with('learningPosts.tags')->get();
        $this->assertSame(89, $user->learningTopicUsages()->count());
        foreach ($posts as $post) {
            $this->assertSame(PostStatus::Generated->value, $post->status->value);
            $this->assertSame(['tip', 'topic'], $post->learningPosts->pluck('kind')->map(fn (LearningKind $kind): string => $kind->value)->sort()->values()->all());
            foreach ($post->learningPosts as $entry) {
                $this->assertNotEmpty($entry->title);
                $this->assertStringContainsString('```', $entry->body);
                $this->assertMatchesRegularExpression('/(?<!`)`[^`\r\n]+`(?!`)/', $entry->body);
                $this->assertStringContainsString('**In practice:**', $entry->body);
                $this->assertGreaterThan(0, $entry->tags->count());
                if ($entry->kind === LearningKind::Topic) {
                    $this->assertDatabaseHas('learning_topics', ['id' => $entry->learning_topic_id]);
                    $this->assertDatabaseHas('learning_topic_usages', ['user_id' => $user->id, 'learning_topic_id' => $entry->learning_topic_id, 'used_on' => $post->date->toDateString()]);
                    $this->assertGreaterThanOrEqual(1, count($entry->links));
                    $this->assertLessThanOrEqual(2, count($entry->links));
                    foreach ($entry->links as $link) {
                        $this->assertNotEmpty($link['title']);
                        $this->assertNotFalse(filter_var($link['url'], FILTER_VALIDATE_URL));
                    }
                }
            }
        }
    }

    public function test_every_post_has_both_deliveries_with_only_the_second_latest_news_email_failed(): void
    {
        $this->seed(ShowcaseDataSeeder::class);

        $user = $this->showcaseUser();
        $channels = $user->deliveryChannels()->orderBy('id')->pluck('id')->all();
        foreach ($user->posts()->with('deliveries.channel')->get() as $post) {
            $this->assertSame($channels, $post->deliveries->sortBy('delivery_channel_id')->pluck('delivery_channel_id')->values()->all());
            foreach ($post->deliveries as $delivery) {
                $failed = $post->kind === PostKind::News && $post->date->toDateString() === '2026-10-29'
                    && $delivery->channel->type === DeliveryChannelType::Email;
                $this->assertSame($failed ? DeliveryStatus::Failed->value : DeliveryStatus::Delivered->value, $delivery->status->value);
                if ($failed) {
                    $this->assertNotEmpty($delivery->last_error);
                }
            }
        }
    }

    public function test_seeded_content_is_available_on_existing_reading_routes_with_complete_setup(): void
    {
        $this->seed(ShowcaseDataSeeder::class);

        $user = $this->showcaseUser();
        $this->actingAs($user)->get('/')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Today', false)
                ->where('day.date', '2026-10-30')
                ->where('day.news.status', 'generated')
                ->where('day.learning.status', 'generated')
                ->where('setup', ['interests' => true, 'sources' => true, 'ai_key' => true, 'delivery' => true]));
        $this->get('/archive')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Archive', false)
                ->has('days', 30)->where('pagination.total', 90)->where('pagination.last_page', 3));
        $selection = PostItem::query()->whereHas('post', fn ($query) => $query->where('user_id', $user->id))->firstOrFail();
        $query = '"'.$selection->title.'"';
        $response = $this->get('/search?'.http_build_query(['q' => $query]))->assertOk();
        $this->assertGreaterThan(0, $response->inertiaProps('pagination.total'));
        $this->assertContains($selection->title, array_column($response->inertiaProps('results'), 'title'));
        $tag = $selection->tags()->firstOrFail();
        $response = $this->get(route('tags.show', ['tag' => $tag->slug]))->assertOk();
        $this->assertSame($tag->slug, $response->inertiaProps('tag.slug'));
        $this->assertGreaterThan(0, $response->inertiaProps('count'));
        $this->assertCount(12, $response->inertiaProps('weeks'));

        if (collect(Route::getRoutes()->getRoutes())->contains(fn ($route): bool => $route->uri() === 'learning' && in_array('GET', $route->methods(), true))) {
            $this->get('/learning')->assertOk();
        }
    }

    private function showcaseUser(): User
    {
        return User::query()->where('email', 'showcase@example.com')->sole();
    }

    private function tableCounts(): array
    {
        $counts = [];
        foreach (self::TABLES as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    private function contentSnapshot(): array
    {
        $user = $this->showcaseUser();

        return $user->posts()->orderBy('date')->orderBy('kind')->with('postItems', 'learningPosts')->get()
            ->map(fn (Post $post): array => [
                $post->date->toDateString(), $post->kind->value, $post->status->value, $post->rendered,
                $post->postItems->map(fn (PostItem $item): array => [$item->item_id, $item->title, $item->summary, $item->position])->all(),
                $post->learningPosts->sortBy('kind')->map(fn ($entry): array => [$entry->kind->value, $entry->learning_topic_id, $entry->title, $entry->body, $entry->links])->values()->all(),
            ])->all();
    }
}
