<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Sources\Candidate;
use App\Digest\Sources\CollectionResult;
use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\FetchException;
use App\Digest\Sources\SourceCollector;
use App\Digest\Sources\SourceFetcher;
use App\Enums\ItemStatus;
use App\Enums\SourceType;
use App\Models\Item;
use App\Models\Source;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class SourceCollectorTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, list<Candidate>|\Throwable> */
    private array $responses = [];

    /** @var array<string, list<CarbonImmutable>> */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-16 07:00:00'));

        $responses = &$this->responses;
        $calls = &$this->calls;
        $this->app->instance(FeedFetcher::class, new class($responses, $calls) implements SourceFetcher
        {
            /**
             * @param  array<string, list<Candidate>|\Throwable>  $responses
             * @param  array<string, list<CarbonImmutable>>  $calls
             */
            public function __construct(private array &$responses, private array &$calls) {}

            public function fetch(Source $source, CarbonImmutable $since): array
            {
                $this->calls[$source->slug][] = $since;
                $response = $this->responses[$source->slug] ?? [];

                return $response instanceof \Throwable ? throw $response : $response;
            }
        });
    }

    public function test_stores_new_candidates(): void
    {
        $this->source('laravel-news');
        $this->responses['laravel-news'] = [$this->candidate('laravel-news', 'https://laravel-news.com/a'), $this->candidate('laravel-news', 'https://laravel-news.com/b')];

        $result = $this->collect();

        $this->assertSame(2, $result->stored);
        $this->assertSame([], $result->failures);
        $item = Item::query()->where('url', 'https://laravel-news.com/a')->firstOrFail();
        $this->assertSame(ItemStatus::Candidate, $item->status);
        $this->assertSame('laravel-news', $item->source_slug);
        $this->assertSame('https://laravel-news.com/a', $item->url_normalized);
        $this->assertSame(['https://laravel-news.com/a'], $item->links);
    }

    public function test_skips_already_known_urls(): void
    {
        $this->source('laravel-news');
        Item::factory()->create(['url' => 'https://example.com/a', 'url_normalized' => 'https://example.com/a']);
        $this->responses['laravel-news'] = [
            $this->candidate('laravel-news', 'https://www.example.com/a/?utm_source=x'),
            $this->candidate('laravel-news', 'https://example.com/new'),
            $this->candidate('laravel-news', 'https://example.com/new/'),
        ];

        $result = $this->collect();

        $this->assertSame(1, $result->stored);
        $this->assertSame(2, Item::query()->count());
    }

    public function test_one_failing_source_does_not_stop_the_others(): void
    {
        $this->source('broken', 'Broken Blog', position: 1);
        $this->source('laravel-news', position: 2);
        $this->responses['broken'] = new FetchException('HTTP 500');
        $this->responses['laravel-news'] = [$this->candidate('laravel-news', 'https://laravel-news.com/a')];

        $result = $this->collect();

        $this->assertSame(1, $result->stored);
        $this->assertSame(['Broken Blog' => 'HTTP 500'], $result->failures);
        $broken = Source::query()->where('slug', 'broken')->firstOrFail();
        $this->assertSame('HTTP 500', $broken->last_error);
        $this->assertNull($broken->last_success_at);
        $this->assertSame('2026-10-16 07:00:00', $broken->last_fetched_at?->toDateTimeString());
    }

    public function test_unexpected_exception_counts_as_a_failure(): void
    {
        $this->source('weird', 'Weird Feed');
        $this->responses['weird'] = new RuntimeException('Undefined index');

        $result = $this->collect();

        $this->assertSame(['Weird Feed' => 'Undefined index'], $result->failures);
    }

    public function test_records_last_success_at_and_clears_the_error(): void
    {
        $source = $this->source('laravel-news');
        $source->update(['last_error' => 'old failure']);

        $this->collect();

        $source->refresh();
        $this->assertSame('2026-10-16 07:00:00', $source->last_success_at?->toDateTimeString());
        $this->assertNull($source->last_error);
    }

    public function test_disabled_sources_are_skipped(): void
    {
        $this->source('laravel-news')->update(['enabled' => false]);
        $this->responses['laravel-news'] = [$this->candidate('laravel-news', 'https://laravel-news.com/a')];

        $result = $this->collect();

        $this->assertSame(0, $result->stored);
        $this->assertNull(Source::query()->firstOrFail()->last_fetched_at);
    }

    public function test_records_the_returning_source_for_new_candidates(): void
    {
        $source = $this->source('laravel-news');
        $this->responses[$source->slug] = [$this->candidate('legacy-slug', 'https://example.com/new')];

        $result = $this->collect();

        $this->assertSame(1, $result->stored);
        $item = Item::query()->where('url_normalized', 'https://example.com/new')->firstOrFail();
        $this->assertDatabaseCount('item_source', 1);
        $this->assertDatabaseHas('item_source', ['item_id' => $item->id, 'source_id' => $source->id, 'first_seen_at' => now()->toDateTimeString()]);
    }

    public function test_records_the_source_for_an_already_known_normalized_url(): void
    {
        $source = $this->source('laravel-news');
        $item = Item::factory()->create(['url_normalized' => 'https://example.com/a', 'source_slug' => 'other']);
        $this->responses[$source->slug] = [$this->candidate($source->slug, 'https://www.example.com/a/?utm_source=x')];

        $result = $this->collect();

        $this->assertSame(0, $result->stored);
        $this->assertDatabaseCount('items', 1);
        $this->assertDatabaseHas('item_source', ['item_id' => $item->id, 'source_id' => $source->id, 'first_seen_at' => now()->toDateTimeString()]);
        $this->assertSame('other', $item->fresh()->source_slug);
    }

    public function test_reseeing_an_item_never_changes_first_seen_at(): void
    {
        $source = $this->source('laravel-news');
        $this->responses[$source->slug] = [$this->candidate($source->slug, 'https://example.com/a')];
        $this->collect();
        $firstSeen = now()->toDateTimeString();
        $this->travel(1)->hour();

        $result = $this->collect();

        $this->assertSame(0, $result->stored);
        $this->assertDatabaseCount('item_source', 1);
        $this->assertDatabaseHas('item_source', ['source_id' => $source->id, 'first_seen_at' => $firstSeen]);
    }

    public function test_the_same_item_can_be_seen_in_two_sources(): void
    {
        $first = $this->source('first');
        $second = $this->source('second', position: 2);
        foreach ([$first, $second] as $source) {
            $this->responses[$source->slug] = [$this->candidate($source->slug, 'https://example.com/shared')];
        }

        $result = $this->collect();

        $this->assertSame(1, $result->stored);
        $this->assertDatabaseCount('items', 1);
        $this->assertDatabaseCount('item_source', 2);
        $item = Item::query()->firstOrFail();
        foreach ([$first, $second] as $source) {
            $this->assertDatabaseHas('item_source', ['item_id' => $item->id, 'source_id' => $source->id, 'first_seen_at' => now()->toDateTimeString()]);
        }
    }

    public function test_collect_for_fetches_only_enabled_subscriptions_to_enabled_sources_for_given_users(): void
    {
        $users = User::factory()->count(2)->create();
        $other = User::factory()->create();
        $first = $this->source('first');
        $second = $this->source('second');
        $disabledSubscription = $this->source('disabled-subscription');
        $disabledSource = $this->source('disabled-source');
        $disabledSource->update(['enabled' => false]);
        $otherSource = $other->customSources()->save(Source::factory()->make(['slug' => 'other-user']));
        $unsubscribed = $this->source('unsubscribed');
        $users[0]->sourceSubscriptions()->create(['source_id' => $first->id, 'enabled' => true]);
        $users[1]->sourceSubscriptions()->create(['source_id' => $second->id, 'enabled' => true]);
        $users[0]->sourceSubscriptions()->create(['source_id' => $disabledSubscription->id, 'enabled' => false]);
        $other->sourceSubscriptions()->create(['source_id' => $disabledSubscription->id, 'enabled' => true]);
        $users[0]->sourceSubscriptions()->create(['source_id' => $disabledSource->id, 'enabled' => true]);
        $other->sourceSubscriptions()->create(['source_id' => $otherSource->id, 'enabled' => true]);
        foreach ([$first, $second, $disabledSubscription, $disabledSource, $otherSource, $unsubscribed] as $source) {
            $this->responses[$source->slug] = [$this->candidate($source->slug, "https://example.com/{$source->slug}")];
        }

        $result = $this->app->make(SourceCollector::class)->collectFor($users);

        $this->assertSame(2, $result->stored);
        $this->assertSame([], $result->failures);
        $this->assertEqualsCanonicalizing(['first', 'second'], array_keys($this->calls));
        foreach ([$disabledSubscription, $disabledSource, $otherSource, $unsubscribed] as $source) {
            $this->assertNull($source->fresh()->last_fetched_at);
        }
    }

    public function test_collect_for_an_empty_user_collection_fetches_nothing(): void
    {
        $user = User::factory()->create();
        $source = $this->source('subscribed');
        $user->sourceSubscriptions()->create(['source_id' => $source->id, 'enabled' => true]);

        $result = $this->app->make(SourceCollector::class)->collectFor(User::query()->whereRaw('1 = 0')->get());

        $this->assertSame(0, $result->stored);
        $this->assertSame([], $result->failures);
        $this->assertSame([], $this->calls);
        $this->assertNull($source->fresh()->last_fetched_at);
    }

    public function test_collect_for_skips_recent_success_until_exactly_twenty_five_minutes(): void
    {
        $user = User::factory()->create();
        $source = $this->source('recent');
        $source->update(['last_success_at' => now()->subMinutes(24), 'last_error' => 'keep until fetched']);
        $user->sourceSubscriptions()->create(['source_id' => $source->id, 'enabled' => true]);
        $collector = $this->app->make(SourceCollector::class);

        $result = $collector->collectFor(User::query()->whereKey($user->id)->get());

        $this->assertSame(0, $result->stored);
        $this->assertSame([], $this->calls);
        $this->assertNull($source->fresh()->last_fetched_at);
        $this->assertSame('keep until fetched', $source->fresh()->last_error);
        $this->travel(1)->minute();

        $collector->collectFor(User::query()->whereKey($user->id)->get());

        $this->assertCount(1, $this->calls['recent']);
        $this->assertTrue($source->fresh()->last_success_at->equalTo(now()));
        $this->assertNull($source->fresh()->last_error);
    }

    public function test_collect_for_uses_each_sources_last_success_with_one_hour_overlap_or_twenty_four_hours(): void
    {
        $user = User::factory()->create();
        $never = $this->source('never');
        $never->update(['last_fetched_at' => now()->subMinute(), 'last_error' => 'previous failure']);
        $older = $this->source('older');
        $older->update(['last_success_at' => now()->subHours(3), 'last_fetched_at' => now()->subMinute()]);
        $oldest = $this->source('oldest');
        $oldest->update(['last_success_at' => now()->subDays(2)]);
        foreach ([$never, $older, $oldest] as $source) {
            $user->sourceSubscriptions()->create(['source_id' => $source->id, 'enabled' => true]);
        }

        $this->app->make(SourceCollector::class)->collectFor(User::query()->whereKey($user->id)->get());

        $this->assertTrue($this->calls['never'][0]->equalTo(now()->subHours(24)));
        $this->assertTrue($this->calls['older'][0]->equalTo(now()->subHours(4)));
        $this->assertTrue($this->calls['oldest'][0]->equalTo(now()->subHours(49)));
    }

    public function test_collect_for_fetches_a_shared_source_once_and_records_new_and_known_items(): void
    {
        $users = User::factory()->count(2)->create();
        $source = $this->source('shared');
        foreach ($users as $user) {
            $user->sourceSubscriptions()->create(['source_id' => $source->id, 'enabled' => true]);
        }
        $known = Item::factory()->create(['url_normalized' => 'https://example.com/known']);
        $this->responses['shared'] = [
            $this->candidate('shared', 'https://example.com/known/'),
            $this->candidate('shared', 'https://example.com/new'),
        ];
        $collector = $this->app->make(SourceCollector::class);

        $result = $collector->collectFor($users);

        $this->assertCount(1, $this->calls['shared']);
        $this->assertSame(1, $result->stored);
        $new = Item::query()->where('url_normalized', 'https://example.com/new')->firstOrFail();
        $firstSeen = now()->toDateTimeString();
        foreach ([$known, $new] as $item) {
            $this->assertDatabaseHas('item_source', ['item_id' => $item->id, 'source_id' => $source->id, 'first_seen_at' => $firstSeen]);
        }
        $this->travel(25)->minutes();

        $result = $collector->collectFor($users);

        $this->assertSame(0, $result->stored);
        $this->assertCount(2, $this->calls['shared']);
        $this->assertDatabaseCount('item_source', 2);
        $this->assertSame(2, DB::table('item_source')->where('first_seen_at', $firstSeen)->count());
    }

    public function test_collect_for_isolates_expected_and_unexpected_failures_and_updates_source_health(): void
    {
        $user = User::factory()->create();
        $broken = $this->source('broken', 'Broken Blog', position: 1);
        $unexpected = $this->source('unexpected', 'Unexpected', position: 2);
        $healthy = $this->source('healthy', position: 3);
        $previousSuccess = now()->subHours(2);
        $broken->update(['last_success_at' => $previousSuccess]);
        $healthy->update(['last_error' => 'old failure']);
        foreach ([$broken, $unexpected, $healthy] as $source) {
            $user->sourceSubscriptions()->create(['source_id' => $source->id, 'enabled' => true]);
        }
        $this->responses['broken'] = new FetchException('HTTP 500');
        $this->responses['unexpected'] = new RuntimeException('Undefined index');
        $this->responses['healthy'] = [$this->candidate('healthy', 'https://example.com/a')];

        $result = $this->app->make(SourceCollector::class)->collectFor(User::query()->whereKey($user->id)->get());

        $this->assertSame(1, $result->stored);
        $this->assertSame(['Broken Blog' => 'HTTP 500', 'Unexpected' => 'Undefined index'], $result->failures);
        foreach ([$broken, $unexpected, $healthy] as $source) {
            $this->assertTrue($source->fresh()->last_fetched_at->equalTo(now()));
        }
        $this->assertSame('HTTP 500', $broken->fresh()->last_error);
        $this->assertTrue($broken->fresh()->last_success_at->equalTo($previousSuccess));
        $this->assertSame('Undefined index', $unexpected->fresh()->last_error);
        $this->assertNull($unexpected->fresh()->last_success_at);
        $this->assertTrue($healthy->fresh()->last_success_at->equalTo(now()));
        $this->assertNull($healthy->fresh()->last_error);
    }

    public function test_failures_for_includes_only_the_users_enabled_subscriptions_to_enabled_failing_sources(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $failing = $this->source('failing', 'Failing Feed');
        $disabledSubscription = $this->source('disabled-subscription');
        $disabledSource = $this->source('disabled-source');
        $healthy = $this->source('healthy');
        $otherSource = $other->customSources()->save(Source::factory()->make());
        $unsubscribed = $this->source('unsubscribed');
        foreach ([$failing, $disabledSubscription, $disabledSource, $otherSource, $unsubscribed] as $source) {
            $source->update(['last_error' => 'HTTP 500']);
        }
        $disabledSource->update(['enabled' => false]);
        foreach ([$failing, $disabledSource, $healthy] as $source) {
            $user->sourceSubscriptions()->create(['source_id' => $source->id, 'enabled' => true]);
        }
        $user->sourceSubscriptions()->create(['source_id' => $disabledSubscription->id, 'enabled' => false]);
        foreach ([$failing, $disabledSubscription, $otherSource] as $source) {
            $other->sourceSubscriptions()->create(['source_id' => $source->id, 'enabled' => true]);
        }

        $failures = $this->app->make(SourceCollector::class)->failuresFor($user);

        $this->assertSame(['Failing Feed' => 'HTTP 500'], $failures);
        $this->assertSame([], $this->calls);
    }

    public function test_failures_for_includes_a_subscribed_custom_source_with_a_non_null_empty_error(): void
    {
        $user = User::factory()->create();
        $source = $user->customSources()->save(Source::factory()->make(['name' => 'Custom Newsletter', 'last_error' => '']));
        $user->sourceSubscriptions()->create(['source_id' => $source->id, 'enabled' => true]);

        $this->assertSame(['Custom Newsletter' => ''], $this->app->make(SourceCollector::class)->failuresFor($user));
    }

    public function test_failures_for_a_user_without_subscriptions_is_empty(): void
    {
        $this->source('failing')->update(['last_error' => 'HTTP 500']);

        $this->assertSame([], $this->app->make(SourceCollector::class)->failuresFor(User::factory()->create()));
    }

    private function collect(): CollectionResult
    {
        return $this->app->make(SourceCollector::class)->collect(CarbonImmutable::parse('2026-10-15 07:00:00'));
    }

    private function source(string $slug, ?string $name = null, int $position = 1): Source
    {
        return Source::query()->create([
            'slug' => $slug,
            'name' => $name ?? $slug,
            'type' => SourceType::Feed,
            'url' => "https://{$slug}.test/feed",
            'tag' => 'laravel',
            'max_items' => 10,
            'position' => $position,
        ]);
    }

    private function candidate(string $slug, string $url): Candidate
    {
        return new Candidate($slug, "Title for {$url}", $url, 'Excerpt', CarbonImmutable::parse('2026-10-16 06:00:00'), [$url]);
    }
}
