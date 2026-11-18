<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Actions\CompleteOnboarding;
use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\SourceCollector;
use App\Digest\Sources\SourceFetcher;
use App\Jobs\CollectSources;
use App\Models\Interest;
use App\Models\Source;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CollectSourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_dispatches_collection(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $user->interests()->attach(Interest::factory()->create());

        app(CompleteOnboarding::class)->handle($user, false, null);

        Queue::assertPushed(CollectSources::class, 1);
        Queue::assertPushed(CollectSources::class, fn (CollectSources $job) => $job->user->is($user));
    }

    public function test_job_collects_for_its_user_and_is_unique_per_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $source = Source::factory()->create();
        $otherSource = Source::factory()->create();
        $user->sourceSubscriptions()->create(['source_id' => $source->id, 'enabled' => true]);
        $other->sourceSubscriptions()->create(['source_id' => $otherSource->id, 'enabled' => true]);
        $fetcher = new class implements SourceFetcher
        {
            public array $calls = [];

            public function fetch(Source $source, CarbonImmutable $since): array
            {
                $this->calls[] = $source->id;

                return [];
            }
        };
        $this->app->instance(FeedFetcher::class, $fetcher);
        $job = new CollectSources($user);

        $job->handle(app(SourceCollector::class));

        $this->assertSame([$source->id], $fetcher->calls);
        $this->assertSame($job->uniqueId(), (new CollectSources($user))->uniqueId());
        $this->assertNotSame($job->uniqueId(), (new CollectSources($other))->uniqueId());
    }
}
