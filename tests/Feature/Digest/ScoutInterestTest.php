<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Ai\Agents\SourceScout;
use App\Digest\Sources\Candidate;
use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\SourceFetcher;
use App\Digest\Support\PublicUrl;
use App\Jobs\ScoutInterest;
use App\Models\Interest;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScoutInterestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-31 12:00:00', 'UTC'));
        config(['digest.ai_providers' => 'gemini:scout-test-model', 'ai.providers.gemini.key' => 'fake-key']);
        Http::preventStrayRequests();
    }

    public function test_is_queued_with_one_attempt_and_a_five_minute_timeout(): void
    {
        $interest = Interest::factory()->create();
        $job = new ScoutInterest($interest);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertTrue($job->interest->is($interest));
        $this->assertSame(1, $job->tries);
        $this->assertSame(300, $job->timeout);
    }

    public function test_serialized_job_calls_the_scout_for_its_interest_including_a_draft(): void
    {
        $interest = Interest::factory()->create();
        $other = Interest::factory()->create();
        SourceScout::fake(['{"proposals":[]}'])->preventStrayPrompts();
        $job = unserialize(serialize(new ScoutInterest($interest)));

        $this->app->call([$job, 'handle']);

        $this->assertTrue($interest->fresh()->scouted_at->equalTo(now()));
        $this->assertNull($other->fresh()->scouted_at);
        SourceScout::assertPromptedTimes(1);
        $this->assertDatabaseCount('source_proposals', 0);
    }

    public function test_passes_a_custom_maximum_to_the_scout(): void
    {
        $this->app->instance(PublicUrl::class, new PublicUrl(fn (string $host) => ['93.184.216.34']));
        $this->app->instance(FeedFetcher::class, new class implements SourceFetcher
        {
            public function fetch(Source $source, CarbonImmutable $since): array
            {
                return array_map(fn (int $index) => new Candidate('Post '.$index, $source->url.'/'.$index, '', CarbonImmutable::now(), []), [1, 2, 3]);
            }
        });
        $proposals = array_map(fn (int $index) => [
            'name' => 'Source '.$index, 'url' => 'https://source'.$index.'.test/feed',
            'site_url' => null, 'reason' => 'Original reporting',
        ], [1, 2, 3]);
        SourceScout::fake([json_encode(['proposals' => $proposals], JSON_THROW_ON_ERROR)])->preventStrayPrompts();

        $this->app->call([new ScoutInterest(Interest::factory()->create(), 2), 'handle']);

        $this->assertDatabaseCount('source_proposals', 2);
        SourceScout::assertPromptedTimes(1);
    }
}
