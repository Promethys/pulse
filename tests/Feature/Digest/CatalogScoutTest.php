<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Ai\Agents\SourceScout;
use App\Digest\Ai\AiConfigurationException;
use App\Digest\Catalog\CatalogScout;
use App\Digest\Sources\Candidate;
use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\SourceFetcher;
use App\Digest\Support\PublicUrl;
use App\Enums\SourceProposalKind;
use App\Enums\SourceProposalOrigin;
use App\Enums\SourceProposalStatus;
use App\Models\Interest;
use App\Models\Source;
use App\Models\SourceProposal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Prompts\AgentPrompt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogScoutTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    public array $fetched = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-31 12:00:00', 'UTC'));
        config(['digest.ai_providers' => 'zai:unsupported,gemini:scout-test-model', 'ai.providers.gemini.key' => 'fake-key']);
        Exceptions::fake();
        Http::preventStrayRequests();
        $this->app->instance(PublicUrl::class, new PublicUrl(fn (string $host) => ['93.184.216.34']));
        $this->app->instance(FeedFetcher::class, new class($this) implements SourceFetcher
        {
            public function __construct(private CatalogScoutTest $test) {}

            public function fetch(Source $source, CarbonImmutable $since): array
            {
                $this->test->fetched[] = $source->url;

                return array_map(fn (int $index) => new Candidate('Post '.$index, $source->url.'/'.$index, '', CarbonImmutable::now(), []), [1, 2, 3]);
            }
        });
    }

    public function test_stores_pending_proposals_with_checks_without_editing_the_catalog(): void
    {
        $interest = Interest::factory()->create(['name' => 'Astronomy']);
        $catalog = Source::factory()->create(['url' => 'https://catalog.test/feed']);
        Source::factory()->create(['url' => 'https://disabled.test/rss', 'enabled' => false]);
        Source::factory()->for(User::factory())->create(['url' => 'https://personal.test/rss']);
        $before = $catalog->fresh()->getAttributes();
        SourceScout::fake(function (string $prompt, mixed $attachments, mixed $provider, string $model) use ($interest): string {
            $this->assertTrue($interest->fresh()->scouted_at->equalTo(now()));
            $this->assertSame('scout-test-model', $model);

            return json_encode(['proposals' => [$this->proposal('new')]], JSON_THROW_ON_ERROR);
        })->preventStrayPrompts();

        $this->assertSame(1, app(CatalogScout::class)->scout($interest, 2));

        $proposal = SourceProposal::query()->sole();
        $this->assertSame(SourceProposalStatus::Pending, $proposal->status);
        $this->assertSame(SourceProposalOrigin::Scout, $proposal->origin);
        $this->assertSame(SourceProposalKind::NewSource, $proposal->kind);
        $this->assertSame($interest->id, $proposal->interest_id);
        $this->assertSame('https://new.test/feed', $proposal->url);
        $this->assertSame('https://new.test', $proposal->site_url);
        $this->assertSame('New', $proposal->name);
        $this->assertSame('Original reporting', $proposal->reason);
        $this->assertSame(['public_url' => true, 'not_in_catalog' => true, 'feed_parses' => true, 'recent_posts' => true], $proposal->checks);
        $this->assertNull($proposal->user_id);
        $this->assertNull($proposal->source_id);
        $this->assertSame($before, $catalog->fresh()->getAttributes());
        $this->assertDatabaseCount('sources', 3);
        $this->assertDatabaseCount('interest_source', 0);
        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('source_checks', 0);
        SourceScout::assertPromptedTimes(1);
        SourceScout::assertPrompted(function (AgentPrompt $prompt): bool {
            $instructions = (string) $prompt->agent->instructions();
            foreach (['Astronomy', '2', 'catalog.test', 'disabled.test'] as $text) {
                $this->assertStringContainsString($text, $instructions);
            }
            $this->assertStringNotContainsString('personal.test', $instructions);

            return $prompt->timeout === 120 && $prompt->provider->name() === 'gemini' && $prompt->model === 'scout-test-model';
        });
        Exceptions::assertNothingReported();
    }

    #[DataProvider('proposalStatuses')]
    public function test_skips_previously_proposed_urls_in_every_status_before_checking_them(SourceProposalStatus $status): void
    {
        $interest = Interest::factory()->create();
        $existing = SourceProposal::factory()->create(['url' => 'https://duplicate.test/feed', 'status' => $status, 'interest_id' => Interest::factory()]);
        $before = $existing->fresh()->getAttributes();
        $this->fakeProposals([$this->proposal('duplicate'), $this->proposal('new')]);

        $this->assertSame(1, app(CatalogScout::class)->scout($interest));

        $this->assertSame(['https://new.test/feed'], $this->fetched);
        $this->assertSame($before, $existing->fresh()->getAttributes());
        $this->assertDatabaseCount('source_proposals', 2);
    }

    public function test_counts_only_stored_proposals_toward_the_limit_and_logs_a_failed_check(): void
    {
        $interest = Interest::factory()->create();
        SourceProposal::factory()->create(['url' => 'https://duplicate.test/feed', 'status' => SourceProposalStatus::Rejected]);
        $bad = $this->proposal('private');
        $bad['url'] = 'http://127.0.0.1/feed';
        $this->fakeProposals([$this->proposal('duplicate'), $bad, $this->proposal('first'), $this->proposal('second'), $this->proposal('third')]);
        Log::spy();

        $this->assertSame(2, app(CatalogScout::class)->scout($interest, 2));

        $this->assertSame(['https://first.test/feed', 'https://second.test/feed'], SourceProposal::query()->where('interest_id', $interest->id)->orderBy('id')->pluck('url')->all());
        $this->assertDatabaseCount('source_proposals', 3);
        Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []) => str_contains($message.json_encode($context), 'public_url'))->once();
    }

    public function test_repeated_urls_in_one_response_are_stored_only_once(): void
    {
        $this->fakeProposals([$this->proposal('duplicate'), $this->proposal('duplicate')]);

        $this->assertSame(1, app(CatalogScout::class)->scout(Interest::factory()->create()));

        $this->assertDatabaseCount('source_proposals', 1);
        $this->assertSame(['https://duplicate.test/feed'], $this->fetched);
    }

    public function test_default_limit_is_five_even_when_the_provider_returns_more(): void
    {
        $this->fakeProposals(array_map(fn (int $index) => $this->proposal('feed'.$index), range(1, 6)));

        $this->assertSame(5, app(CatalogScout::class)->scout(Interest::factory()->create()));

        $this->assertDatabaseCount('source_proposals', 5);
    }

    public function test_zero_maximum_stores_no_proposals(): void
    {
        $this->fakeProposals([$this->proposal('new')]);

        $this->assertSame(0, app(CatalogScout::class)->scout(Interest::factory()->create(), 0));

        $this->assertDatabaseCount('source_proposals', 0);
    }

    public function test_provider_failure_is_reported_and_returns_zero_after_persisting_scouted_at(): void
    {
        $interest = Interest::factory()->create();
        $failure = new ProviderOverloadedException('Scout unavailable');
        SourceScout::fake(function (string $prompt, mixed $attachments, mixed $provider, string $model) use ($interest, $failure): never {
            $this->assertTrue($interest->fresh()->scouted_at->equalTo(now()));

            throw $failure;
        })->preventStrayPrompts();

        $this->assertSame(0, app(CatalogScout::class)->scout($interest));

        $this->assertTrue($interest->fresh()->scouted_at->equalTo(now()));
        $this->assertDatabaseCount('source_proposals', 0);
        $this->assertSame([], $this->fetched);
        Exceptions::assertReported(fn (ProviderOverloadedException $actual) => $actual === $failure);
    }

    public function test_uses_the_filtered_provider_chain_for_failover(): void
    {
        config([
            'digest.ai_providers' => 'zai:unsupported,gemini:primary-model,search_backup:backup-model',
            'ai.providers.search_backup' => ['driver' => 'gemini', 'key' => 'fake-key'],
        ]);
        $models = [];
        SourceScout::fake(function (string $prompt, mixed $attachments, mixed $provider, string $model) use (&$models): string {
            $models[] = $model;
            if ($model === 'primary-model') {
                throw new ProviderOverloadedException('Primary unavailable');
            }

            return json_encode(['proposals' => [$this->proposal('backup')]], JSON_THROW_ON_ERROR);
        })->preventStrayPrompts();

        $this->assertSame(1, app(CatalogScout::class)->scout(Interest::factory()->create()));

        $this->assertSame(['primary-model', 'backup-model'], $models);
        $this->assertDatabaseHas('source_proposals', ['url' => 'https://backup.test/feed']);
    }

    public function test_no_search_provider_falls_back_to_the_full_chain_without_search(): void
    {
        config(['digest.ai_providers' => 'zai:unsupported']);
        $interest = Interest::factory()->create();
        SourceScout::fake([json_encode(['proposals' => [$this->proposal('plain')]], JSON_THROW_ON_ERROR)])->preventStrayPrompts();

        $this->assertSame(1, app(CatalogScout::class)->scout($interest));

        $this->assertTrue($interest->fresh()->scouted_at->equalTo(now()));
        Exceptions::assertNotReported(AiConfigurationException::class);
        SourceScout::assertPromptedTimes(1);
    }

    public function test_a_failed_search_falls_back_without_search(): void
    {
        $prompts = [];
        SourceScout::fake(function (string $prompt) use (&$prompts) {
            $prompts[] = $prompt;

            if (count($prompts) === 1) {
                throw new ProviderOverloadedException('Search quota exceeded');
            }

            return json_encode(['proposals' => [$this->proposal('plain')]], JSON_THROW_ON_ERROR);
        })->preventStrayPrompts();

        $this->assertSame(1, app(CatalogScout::class)->scout(Interest::factory()->create()));

        $this->assertSame([CatalogScout::SEARCH_PROMPT, CatalogScout::PLAIN_PROMPT], $prompts);
        $this->assertDatabaseHas('source_proposals', ['url' => 'https://plain.test/feed']);
        Exceptions::assertNothingReported();
    }

    public function test_a_search_without_usable_proposals_falls_back_without_search(): void
    {
        SourceScout::fake([
            'No feeds found',
            json_encode(['proposals' => [$this->proposal('plain')]], JSON_THROW_ON_ERROR),
        ])->preventStrayPrompts();

        $this->assertSame(1, app(CatalogScout::class)->scout(Interest::factory()->create()));

        SourceScout::assertPromptedTimes(2);
    }

    public function test_malformed_response_stores_nothing_but_marks_the_interest_scouted(): void
    {
        $interest = Interest::factory()->create();
        SourceScout::fake(['No valid JSON today', 'Still no JSON'])->preventStrayPrompts();

        $this->assertSame(0, app(CatalogScout::class)->scout($interest));

        $this->assertTrue($interest->fresh()->scouted_at->equalTo(now()));
        $this->assertDatabaseCount('source_proposals', 0);
        Exceptions::assertNothingReported();
    }

    /** @return array<string, array{SourceProposalStatus}> */
    public static function proposalStatuses(): array
    {
        return ['pending' => [SourceProposalStatus::Pending], 'approved' => [SourceProposalStatus::Approved], 'rejected' => [SourceProposalStatus::Rejected]];
    }

    /** @return array{name: string, url: string, site_url: string, reason: string} */
    private function proposal(string $domain): array
    {
        return ['name' => ucfirst($domain), 'url' => 'https://'.$domain.'.test/feed', 'site_url' => 'https://'.$domain.'.test', 'reason' => 'Original reporting'];
    }

    /** @param  list<array{name: string, url: string, site_url: string, reason: string}>  $proposals */
    private function fakeProposals(array $proposals): void
    {
        SourceScout::fake([json_encode(['proposals' => $proposals], JSON_THROW_ON_ERROR)])->preventStrayPrompts();
    }
}
