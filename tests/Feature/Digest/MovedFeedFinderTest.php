<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Catalog\CatalogChecker;
use App\Digest\Catalog\MovedFeedFinder;
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
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MovedFeedFinderTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://feeds.test/old';

    private const SITE = 'https://site.test/blog/';

    private const MOVED = 'https://site.test/new.xml';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-31 12:00:00', 'UTC'));
        Http::preventStrayRequests();
        Sleep::fake();
        $this->app->instance(PublicUrl::class, new PublicUrl(fn (string $host) => ['93.184.216.34']));
    }

    public function test_validates_a_redirected_feed_before_returning_it_without_changing_the_source(): void
    {
        $source = $this->source();
        $before = $source->fresh()->getAttributes();
        Http::fake([
            self::URL => Http::response('', 301, ['Location' => self::MOVED]),
            self::MOVED => Http::response($this->feed()),
            self::SITE => Http::response('<link rel="alternate" type="application/rss+xml" href="/ignored.xml">'),
        ]);

        $this->assertSame(self::MOVED, app(MovedFeedFinder::class)->find($source));

        Http::assertNotSent(fn (Request $request) => $request->url() === self::SITE);
        $this->assertSame($before, $source->fresh()->getAttributes());
        $this->assertDatabaseCount('sources', 1);
        $this->assertDatabaseCount('items', 0);
    }

    #[DataProvider('feedTypes')]
    public function test_discovers_the_first_feed_link_and_resolves_its_relative_href(string $type): void
    {
        $source = $this->source();
        Http::fake([
            self::URL => Http::response('', 404),
            self::SITE => Http::response('<link rel="stylesheet" href="/style.css"><link rel="alternate" type="text/html" href="/page"><link rel="alternate" type="'.$type.'" href="../new.xml"><link rel="alternate" type="application/rss+xml" href="/ignored.xml">'),
            self::MOVED => Http::response($this->feed()),
        ]);

        $this->assertSame(self::MOVED, app(MovedFeedFinder::class)->find($source));

        Http::assertSent(fn (Request $request) => $request->url() === self::MOVED);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'ignored.xml'));
        $this->assertSame(self::URL, $source->refresh()->url);
    }

    public function test_validates_the_candidate_through_an_unsaved_replica_with_the_collector(): void
    {
        $source = $this->source();
        $validated = [];
        $this->app->instance(FeedFetcher::class, new class($validated) implements SourceFetcher
        {
            /** @param  list<Source>  $validated */
            public function __construct(private array &$validated) {}

            public function fetch(Source $source, CarbonImmutable $since): array
            {
                $this->validated[] = $source;

                return [];
            }
        });
        Http::fake([
            self::URL => Http::response('', 302, ['Location' => self::MOVED]),
            self::MOVED => Http::response($this->feed()),
        ]);

        $this->assertSame(self::MOVED, app(MovedFeedFinder::class)->find($source));

        $this->assertCount(1, $validated);
        $this->assertFalse($validated[0]->exists);
        $this->assertNull($validated[0]->getKey());
        $this->assertSame(self::MOVED, $validated[0]->url);
        $this->assertSame($source->name, $validated[0]->name);
        $this->assertSame($source->type, $validated[0]->type);
        $this->assertSame(self::URL, $source->refresh()->url);
    }

    public function test_null_site_url_skips_autodiscovery(): void
    {
        $source = $this->source(['site_url' => null]);
        Http::fake([self::URL => Http::response('', 404)]);

        $this->assertNull(app(MovedFeedFinder::class)->find($source));

        Http::assertSentCount(1);
    }

    public function test_a_discovered_url_equal_to_the_original_is_not_a_candidate(): void
    {
        $source = $this->source();
        Http::fake([
            self::URL => Http::response('', 404),
            self::SITE => Http::response('<link rel="alternate" type="application/rss+xml" href="'.self::URL.'">'),
        ]);

        $this->assertNull(app(MovedFeedFinder::class)->find($source));

        Http::assertSentCount(2);
    }

    #[DataProvider('privateCandidates')]
    public function test_never_fetches_a_private_candidate(bool $redirect): void
    {
        $source = $this->source();
        $private = 'http://169.254.169.254/latest/meta-data';
        Http::fake([
            self::URL => $redirect ? Http::response('', 302, ['Location' => $private]) : Http::response('', 404),
            self::SITE => Http::response('<link rel="alternate" type="application/rss+xml" href="'.$private.'">'),
            $private => Http::response($this->feed()),
        ]);

        $this->assertNull(app(MovedFeedFinder::class)->find($source));

        Http::assertNotSent(fn (Request $request) => $request->url() === $private);
    }

    #[DataProvider('invalidFeeds')]
    public function test_a_candidate_that_does_not_parse_is_rejected(string $body, int $status): void
    {
        $source = $this->source();
        Http::fake([
            self::URL => Http::response('', 302, ['Location' => self::MOVED]),
            self::MOVED => Http::response($body, $status),
        ]);

        $this->assertNull(app(MovedFeedFinder::class)->find($source));

        $this->assertSame(self::URL, $source->refresh()->url);
        $this->assertDatabaseCount('source_proposals', 0);
    }

    public function test_transport_failures_return_null_instead_of_throwing(): void
    {
        $source = $this->source();
        Http::fake(fn () => throw new RuntimeException('Transport failed'));

        $this->assertNull(app(MovedFeedFinder::class)->find($source));
    }

    public function test_repeated_failed_checks_store_one_pending_url_change_proposal_and_leave_the_source_url_alone(): void
    {
        $source = $this->source();
        Http::fake([
            self::URL => Http::response('', 404),
            self::SITE => Http::response('<link rel="alternate" type="application/rss+xml" href="../new.xml">'),
            self::MOVED => Http::response($this->feed()),
        ]);

        $first = app(CatalogChecker::class)->check($source);
        $second = app(CatalogChecker::class)->check($source->fresh());

        $this->assertFalse($first->ok);
        $this->assertFalse($second->ok);
        $this->assertSame('HTTP 404', $first->error);
        $this->assertSame(self::MOVED, $first->suggested_url);
        $this->assertSame(self::MOVED, $second->suggested_url);
        $this->assertDatabaseCount('source_checks', 2);
        $this->assertDatabaseCount('source_proposals', 1);
        $proposal = SourceProposal::query()->sole();
        $this->assertSame(SourceProposalKind::UrlChange, $proposal->kind);
        $this->assertSame(SourceProposalOrigin::Check, $proposal->origin);
        $this->assertSame(SourceProposalStatus::Pending, $proposal->status);
        $this->assertSame($source->id, $proposal->source_id);
        $this->assertSame(self::MOVED, $proposal->url);
        $this->assertSame(self::SITE, $proposal->site_url);
        $this->assertSame($source->name, $proposal->name);
        $this->assertSame($first->error, $proposal->reason);
        $this->assertNull($proposal->user_id);
        $this->assertNull($proposal->interest_id);
        $this->assertNull($proposal->checks);
        $this->assertSame(self::URL, $source->refresh()->url);
    }

    public function test_proposals_are_deduplicated_by_both_source_and_candidate_url(): void
    {
        $first = $this->source();
        $second = $this->source();
        $anotherUrl = 'https://site.test/another.xml';
        $link = fn (string $url) => '<link rel="alternate" type="application/rss+xml" href="'.$url.'">';
        Http::fake([
            self::URL => Http::response('', 404),
            self::SITE => Http::sequence()
                ->push($link(self::MOVED))
                ->push($link(self::MOVED))
                ->push($link($anotherUrl)),
            self::MOVED => Http::response($this->feed()),
            $anotherUrl => Http::response($this->feed()),
        ]);

        app(CatalogChecker::class)->check($first);
        app(CatalogChecker::class)->check($second);
        app(CatalogChecker::class)->check($first->fresh());

        $this->assertDatabaseCount('source_proposals', 3);
        foreach ([[$first, self::MOVED], [$second, self::MOVED], [$first, $anotherUrl]] as [$source, $url]) {
            $this->assertDatabaseHas('source_proposals', ['source_id' => $source->id, 'url' => $url, 'status' => 'pending']);
        }
    }

    public function test_proposal_defaults_and_json_checks_survive_interest_deletion(): void
    {
        $defaultId = DB::table('source_proposals')->insertGetId([
            'origin' => SourceProposalOrigin::Check->value,
            'kind' => SourceProposalKind::UrlChange->value,
            'url' => self::MOVED,
        ]);
        $this->assertSame(SourceProposalStatus::Pending, SourceProposal::query()->findOrFail($defaultId)->status);
        $interest = Interest::factory()->create();
        $proposal = SourceProposal::factory()->create([
            'origin' => SourceProposalOrigin::Check, 'kind' => SourceProposalKind::UrlChange,
            'interest_id' => $interest->id, 'status' => SourceProposalStatus::Pending,
            'checks' => ['public' => true, 'items' => 0],
        ]);

        $interest->delete();

        $this->assertModelExists($proposal);
        $this->assertNull($proposal->refresh()->interest_id);
        $this->assertSame(['public' => true, 'items' => 0], $proposal->checks);
        $this->assertSame(SourceProposalStatus::Pending, $proposal->status);
    }

    public function test_proposals_are_deleted_with_their_source_or_user(): void
    {
        $source = $this->source();
        $user = User::factory()->create();
        $sourceProposal = SourceProposal::factory()->create(['source_id' => $source->id, 'user_id' => null]);
        $userProposal = SourceProposal::factory()->create(['source_id' => null, 'user_id' => $user->id]);
        $kept = SourceProposal::factory()->create(['source_id' => null, 'user_id' => null]);

        $source->delete();

        $this->assertModelMissing($sourceProposal);
        $this->assertModelExists($userProposal);
        $user->delete();
        $this->assertModelMissing($userProposal);
        $this->assertModelExists($kept);
    }

    /** @return array<string, array{string, int}> */
    public static function invalidFeeds(): array
    {
        return [
            'HTML page' => ['<html><body>Not a feed</body></html>', 200],
            'malformed XML' => ['<rss><channel>', 200],
            'server error' => ['', 500],
        ];
    }

    /** @return array<string, array{string}> */
    public static function feedTypes(): array
    {
        return ['RSS' => ['application/rss+xml'], 'Atom' => ['application/atom+xml']];
    }

    /** @return array<string, array{bool}> */
    public static function privateCandidates(): array
    {
        return ['redirect' => [true], 'autodiscovery' => [false]];
    }

    /** @param  array<string, mixed>  $attributes */
    private function source(array $attributes = []): Source
    {
        return Source::factory()->create(['url' => self::URL, 'site_url' => self::SITE, ...$attributes]);
    }

    private function feed(): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/feeds/hnrss.xml'));
    }
}
