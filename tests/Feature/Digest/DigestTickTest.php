<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Delivery\PostDeliverer;
use App\Digest\Learning\LearningPipeline;
use App\Digest\News\NewsPipeline;
use App\Digest\Sources\Candidate;
use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\FetchException;
use App\Digest\Sources\SourceFetcher;
use App\Enums\DeliveryStatus;
use App\Enums\ItemStatus;
use App\Enums\PostKind;
use App\Jobs\RunDigest;
use App\Models\AiKey;
use App\Models\Delivery;
use App\Models\DigestSettings;
use App\Models\Interest;
use App\Models\Item;
use App\Models\Post;
use App\Models\Source;
use App\Models\SourceSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DigestTickTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<int> */
    public array $fetched = [];

    public bool $fetchFails = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-07-01 16:00:00', 'UTC'));
        $this->freezeSecond();
        config(['digest.max_attempts' => 4, 'digest.news_time' => '23:59', 'digest.learning_time' => '23:59']);
        Queue::fake();
        Bus::fake([RunDigest::class]);
        $this->mock(NewsPipeline::class)->shouldNotReceive('run', 'runFor');
        $this->mock(LearningPipeline::class)->shouldNotReceive('run', 'runFor');
        $this->mock(PostDeliverer::class)->shouldNotReceive('deliver');
        $this->app->instance(FeedFetcher::class, new class($this) implements SourceFetcher
        {
            public function __construct(private DigestTickTest $test) {}

            public function fetch(Source $source, CarbonImmutable $since): array
            {
                Bus::assertNothingDispatched();
                $this->test->fetched[] = $source->id;
                if ($this->test->fetchFails) {
                    throw new FetchException('Source unavailable');
                }

                return [new Candidate($source->slug, 'A headline', 'https://source.test/article', '', null, [])];
            }
        });
    }

    public function test_collects_the_distinct_due_users_sources_before_dispatching_each_local_digest(): void
    {
        $tokyo = $this->eligibleUser('Asia/Tokyo');
        $paris = $this->eligibleUser('Europe/Paris');
        $later = $this->eligibleUser('UTC', ['news_time' => '23:59', 'learning_time' => '23:59']);
        $shared = Source::factory()->create();
        foreach ([$tokyo, $paris] as $user) {
            SourceSubscription::factory()->create(['user_id' => $user->id, 'source_id' => $shared->id]);
        }
        $learningOnly = $this->eligibleUser('UTC', ['news_enabled' => false]);
        $learningSource = Source::factory()->create();
        SourceSubscription::factory()->create(['user_id' => $learningOnly->id, 'source_id' => $learningSource->id]);
        SourceSubscription::factory()->create(['user_id' => $later->id]);
        Source::factory()->create();
        SourceSubscription::factory()->create(['user_id' => $paris->id, 'enabled' => false]);

        $this->artisan('digest:tick')
            ->expectsOutput('Dispatched 5 digests')
            ->expectsOutput('Retried deliveries for 0 posts')
            ->assertExitCode(0);

        $this->assertEqualsCanonicalizing([$shared->id, $learningSource->id], $this->fetched);
        Bus::assertDispatchedTimes(RunDigest::class, 5);
        foreach ([[$tokyo, '2026-07-02', 'Asia/Tokyo'], [$paris, '2026-07-01', 'Europe/Paris'], [$learningOnly, '2026-07-01', 'UTC']] as [$user, $date, $timezone]) {
            foreach ($user->is($learningOnly) ? [PostKind::Learning] : PostKind::cases() as $kind) {
                Bus::assertDispatched(RunDigest::class, fn (RunDigest $job) => $job->user->is($user)
                    && $job->kind === $kind
                    && $job->date->toDateTimeString() === $date.' 00:00:00'
                    && $job->date->timezoneName === $timezone);
            }
        }
        $this->assertDatabaseCount('posts', 0);
        Queue::assertNothingPushed();
    }

    public function test_no_due_users_skips_collection_and_dispatch(): void
    {
        SourceSubscription::factory()->create();

        $this->artisan('digest:tick')
            ->expectsOutput('Dispatched 0 digests')
            ->expectsOutput('Retried deliveries for 0 posts')
            ->assertExitCode(0);

        $this->assertSame([], $this->fetched);
        Bus::assertNothingDispatched();
    }

    public function test_source_failure_still_dispatches_and_returns_success(): void
    {
        $user = $this->eligibleUser('UTC');
        SourceSubscription::factory()->create(['user_id' => $user->id]);
        $this->fetchFails = true;

        $this->artisan('digest:tick')->expectsOutput('Dispatched 2 digests')->assertExitCode(0);

        Bus::assertDispatchedTimes(RunDigest::class, 2);
        $this->assertCount(1, $this->fetched);
    }

    public function test_retries_each_recent_post_once_using_delivery_status_and_attempts_even_when_no_digest_is_due(): void
    {
        $user = User::factory()->create();
        $retry = Post::factory()->create(['user_id' => $user->id, 'created_at' => now()->subDays(2), 'attempts' => 4, 'rendered' => ['title' => 'Ready', 'messages' => ['Body']]]);
        foreach ([0, 3] as $attempts) {
            Delivery::factory()->create(['post_id' => $retry->id, 'status' => DeliveryStatus::Failed, 'attempts' => $attempts]);
        }
        $other = Post::factory()->create(['user_id' => User::factory(), 'kind' => PostKind::Learning, 'rendered' => ['title' => 'Ready', 'messages' => ['Body']]]);
        Delivery::factory()->create(['post_id' => $other->id, 'status' => DeliveryStatus::Failed, 'attempts' => 1]);
        foreach ([
            [DeliveryStatus::Failed, 4, now()],
            [DeliveryStatus::Failed, 5, now()],
            [DeliveryStatus::Delivered, 0, now()],
            [DeliveryStatus::Pending, 0, now()],
            [DeliveryStatus::Failed, 0, now()->subDays(2)->subSecond()],
        ] as [$status, $attempts, $created]) {
            $post = Post::factory()->create(['user_id' => $user->id, 'created_at' => $created]);
            Delivery::factory()->create(['post_id' => $post->id, 'status' => $status, 'attempts' => $attempts]);
        }
        Post::factory()->create(['user_id' => $user->id]);
        $deliverer = $this->mock(PostDeliverer::class);
        $deliverer->shouldReceive('deliver')->once()->withArgs(fn (Post $post) => $post->is($retry))->andReturn(1);
        $deliverer->shouldReceive('deliver')->once()->withArgs(fn (Post $post) => $post->is($other))->andReturn(0);

        $this->artisan('digest:tick')
            ->expectsOutput('Dispatched 0 digests')
            ->expectsOutput('Retried deliveries for 2 posts')
            ->assertExitCode(0);

        Bus::assertNothingDispatched();
    }

    public function test_dispatches_before_retrying_deliveries_then_prunes_only_old_dated_skipped_items(): void
    {
        $this->eligibleUser('UTC');
        $post = Post::factory()->create(['user_id' => User::factory()]);
        Delivery::factory()->create(['post_id' => $post->id, 'status' => DeliveryStatus::Failed]);
        $old = Item::factory()->create(['status' => ItemStatus::Skipped, 'published_at' => now()->subDays(40), 'created_at' => now()->subDays(31)]);
        $kept = [
            Item::factory()->create(['status' => ItemStatus::Skipped, 'created_at' => now()->subDays(29)]),
            Item::factory()->create(['status' => ItemStatus::Skipped, 'published_at' => null, 'created_at' => now()->subDays(31)]),
            Item::factory()->create(['status' => ItemStatus::Selected, 'created_at' => now()->subDays(31)]),
            Item::factory()->create(['status' => ItemStatus::Candidate, 'created_at' => now()->subDays(31)]),
        ];
        $this->mock(PostDeliverer::class)->shouldReceive('deliver')->once()->withArgs(function (Post $actual) use ($post, $old) {
            Bus::assertDispatchedTimes(RunDigest::class, 2);
            $this->assertModelExists($old);

            return $actual->is($post);
        })->andReturn(0);

        $this->artisan('digest:tick')
            ->expectsOutput('Dispatched 2 digests')
            ->expectsOutput('Retried deliveries for 1 posts')
            ->expectsOutput('Pruned 1 old skipped items')
            ->assertExitCode(0);

        $this->assertModelMissing($old);
        foreach ($kept as $item) {
            $this->assertModelExists($item);
        }
    }

    /** @param  array<string, mixed>  $settings */
    private function eligibleUser(string $timezone, array $settings = []): User
    {
        $user = User::factory()->create();
        DigestSettings::factory()->create(['user_id' => $user->id, 'timezone' => $timezone, 'onboarded_at' => now(), 'news_time' => '00:00', 'learning_time' => '00:00', ...$settings]);
        AiKey::factory()->create(['user_id' => $user->id]);
        $user->interests()->attach(Interest::factory()->create(['has_learning' => true]));

        return $user;
    }
}
