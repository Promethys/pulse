<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Sources\Candidate;
use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\FetchException;
use App\Digest\Sources\SourceFetcher;
use App\Enums\SourceType;
use App\Models\Item;
use App\Models\Source;
use App\Models\SourceSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DigestSourcesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_counts_and_errors_without_storing_and_fails(): void
    {
        $this->app->instance(FeedFetcher::class, new class implements SourceFetcher
        {
            public function fetch(Source $source, CarbonImmutable $since): array
            {
                if ($source->slug === 'broken') {
                    throw new FetchException('HTTP 404');
                }

                return [new Candidate($source->slug, 'A', 'https://ok.test/a', '', null, ['https://ok.test/a'])];
            }
        });
        foreach (['ok', 'broken'] as $i => $slug) {
            Source::query()->create(['slug' => $slug, 'name' => $slug, 'type' => SourceType::Feed, 'url' => "https://{$slug}.test", 'tag' => 'ai', 'max_items' => 5, 'position' => $i]);
        }

        $this->artisan('digest:sources')
            ->expectsTable(['Source', 'Items', 'Error'], [['ok', '1', ''], ['broken', '0', 'HTTP 404']])
            ->assertExitCode(1);

        $this->assertSame(0, Item::query()->count());
        $this->assertNull(Source::query()->where('slug', 'ok')->value('last_fetched_at'));
    }

    public function test_succeeds_when_every_source_works(): void
    {
        $this->app->instance(FeedFetcher::class, new class implements SourceFetcher
        {
            public function fetch(Source $source, CarbonImmutable $since): array
            {
                return [];
            }
        });
        Source::query()->create(['slug' => 'ok', 'name' => 'ok', 'type' => SourceType::Feed, 'url' => 'https://ok.test', 'tag' => 'ai', 'max_items' => 5, 'position' => 1]);

        $this->artisan('digest:sources', ['--since' => '3d'])->assertExitCode(0);
    }

    public function test_user_option_checks_only_the_users_enabled_subscriptions_to_enabled_sources(): void
    {
        $user = User::factory()->create();
        $subscribed = Source::factory()->create(['slug' => 'subscribed', 'position' => 1]);
        $custom = Source::factory()->create(['user_id' => $user->id, 'slug' => 'custom', 'position' => 2]);
        foreach ([$subscribed, $custom] as $source) {
            SourceSubscription::factory()->create(['user_id' => $user->id, 'source_id' => $source->id]);
        }
        SourceSubscription::factory()->create(['user_id' => $user->id, 'enabled' => false]);
        SourceSubscription::factory()->create(['user_id' => $user->id, 'source_id' => Source::factory()->create(['enabled' => false])->id]);
        SourceSubscription::factory()->create();
        Source::factory()->create();
        $fetched = [];
        $this->app->instance(FeedFetcher::class, new class($fetched) implements SourceFetcher
        {
            /** @param  list<int>  $fetched */
            public function __construct(private array &$fetched) {}

            public function fetch(Source $source, CarbonImmutable $since): array
            {
                $this->fetched[] = $source->id;

                return [new Candidate($source->slug, 'Title', 'https://source.test/article', '', null, [])];
            }
        });

        $this->artisan('digest:sources', ['--user' => $user->email])
            ->expectsTable(['Source', 'Items', 'Error'], [['subscribed', '1', ''], ['custom', '1', '']])
            ->assertExitCode(0);

        $this->assertSame([$subscribed->id, $custom->id], $fetched);
        $this->assertDatabaseCount('items', 0);
        $this->assertNull($subscribed->refresh()->last_fetched_at);
        $this->assertNull($custom->refresh()->last_fetched_at);
    }

    public function test_user_with_no_enabled_subscriptions_does_not_fetch_other_sources(): void
    {
        $user = User::factory()->create();
        SourceSubscription::factory()->create(['user_id' => $user->id, 'enabled' => false]);
        SourceSubscription::factory()->create();
        $this->app->instance(FeedFetcher::class, new class implements SourceFetcher
        {
            public function fetch(Source $source, CarbonImmutable $since): array
            {
                throw new FetchException('No source should be fetched');
            }
        });

        $this->artisan('digest:sources', ['--user' => $user->email])
            ->expectsTable(['Source', 'Items', 'Error'], [])
            ->assertExitCode(0);
    }

    public function test_subscribed_source_failure_is_reported_without_checking_unsubscribed_sources(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->create(['slug' => 'broken']);
        SourceSubscription::factory()->create(['user_id' => $user->id, 'source_id' => $source->id]);
        Source::factory()->create();
        $this->app->instance(FeedFetcher::class, new class implements SourceFetcher
        {
            public function fetch(Source $source, CarbonImmutable $since): array
            {
                throw new FetchException('HTTP 404');
            }
        });

        $this->artisan('digest:sources', ['--user' => $user->email])
            ->expectsTable(['Source', 'Items', 'Error'], [['broken', '0', 'HTTP 404']])
            ->assertExitCode(1);

        $this->assertDatabaseCount('items', 0);
        $this->assertNull($source->refresh()->last_fetched_at);
    }
}
