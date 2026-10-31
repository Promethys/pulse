<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Catalog\ProposalIntake;
use App\Digest\Sources\Candidate;
use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\FetchException;
use App\Digest\Sources\SourceFetcher;
use App\Digest\Support\PublicUrl;
use App\Enums\SourceProposalKind;
use App\Enums\SourceProposalOrigin;
use App\Enums\SourceProposalStatus;
use App\Jobs\ImportProposal;
use App\Models\Interest;
use App\Models\Source;
use App\Models\SourceProposal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportProposalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->app->instance(PublicUrl::class, new PublicUrl(fn (string $host) => ['93.184.216.34']));
        $this->app->instance(FeedFetcher::class, new class implements SourceFetcher
        {
            public function fetch(Source $source, CarbonImmutable $since): array
            {
                if (str_contains((string) $source->url, 'guessed')) {
                    throw new FetchException('HTTP 404');
                }

                $posts = str_contains((string) $source->url, 'quiet') ? 1 : 3;

                return array_map(fn (int $index) => new Candidate('Post '.$index, $source->url.'/'.$index, '', null, []), range(1, $posts));
            }
        });
    }

    public function test_a_passing_row_becomes_a_pending_import_proposal(): void
    {
        $interest = Interest::factory()->create(['slug' => 'pets']);

        (new ImportProposal($this->row()))->handle(app(ProposalIntake::class));

        $proposal = SourceProposal::query()->sole();
        $this->assertSame(SourceProposalOrigin::Import, $proposal->origin);
        $this->assertSame(SourceProposalKind::NewSource, $proposal->kind);
        $this->assertSame(SourceProposalStatus::Pending, $proposal->status);
        $this->assertSame($interest->id, $proposal->interest_id);
        $this->assertSame('Regular posts.', $proposal->reason);
        $this->assertTrue($proposal->checks['recent_posts']);
    }

    public function test_a_failed_check_stores_nothing(): void
    {
        Interest::factory()->create(['slug' => 'pets']);

        (new ImportProposal($this->row(['url' => 'https://quiet.test/feed'])))->handle(app(ProposalIntake::class));

        $this->assertDatabaseCount('source_proposals', 0);
    }

    public function test_a_url_already_proposed_in_any_status_is_skipped(): void
    {
        Interest::factory()->create(['slug' => 'pets']);
        SourceProposal::factory()->create(['url' => 'https://dogs.test/feed', 'status' => SourceProposalStatus::Rejected]);

        (new ImportProposal($this->row()))->handle(app(ProposalIntake::class));

        $this->assertDatabaseCount('source_proposals', 1);
    }

    public function test_a_guessed_url_uses_the_feed_the_site_advertises(): void
    {
        Interest::factory()->create(['slug' => 'pets']);
        Http::fake(['https://dogs.test/*' => Http::response('<html><head><link rel="alternate" type="application/rss+xml" href="/real"></head></html>')]);

        (new ImportProposal($this->row(['url' => 'https://dogs.test/guessed'])))->handle(app(ProposalIntake::class));

        $this->assertDatabaseHas('source_proposals', ['url' => 'https://dogs.test/real']);
    }

    public function test_a_row_for_a_deleted_interest_does_nothing(): void
    {
        (new ImportProposal($this->row()))->handle(app(ProposalIntake::class));

        $this->assertDatabaseCount('source_proposals', 0);
    }

    /**
     * @param  array<string, mixed>  $change
     * @return array{interest: string, name: string, url: string, site_url: ?string, reason: string}
     */
    private function row(array $change = []): array
    {
        return [...['interest' => 'pets', 'name' => 'Dogs', 'url' => 'https://dogs.test/feed', 'site_url' => 'https://dogs.test/', 'reason' => 'Regular posts.'], ...$change];
    }
}
