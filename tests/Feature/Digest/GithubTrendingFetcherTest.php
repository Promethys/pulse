<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Sources\Candidate;
use App\Digest\Sources\FetchException;
use App\Digest\Sources\GithubTrendingFetcher;
use App\Enums\SourceType;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GithubTrendingFetcherTest extends TestCase
{
    private const URL = 'https://github.com/trending?since=daily';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        CarbonImmutable::setTestNow('2026-10-16 07:00:00');
    }

    public function test_parses_rows(): void
    {
        Http::fake([self::URL => Http::response((string) file_get_contents(base_path('tests/Fixtures/github-trending.html')))]);

        $candidates = $this->fetch();

        $this->assertSame(
            ['anthropics/claude-code', 'php/frankenphp', 'someone/no-description'],
            array_map(fn (Candidate $c) => $c->title, $candidates),
        );
        $this->assertSame('https://github.com/anthropics/claude-code', $candidates[0]->url);
        $this->assertSame(['https://github.com/anthropics/claude-code'], $candidates[0]->links);
        $this->assertSame('Claude Code is an agentic coding tool that lives in your terminal.', $candidates[0]->excerpt);
        $this->assertSame('', $candidates[2]->excerpt);
        $this->assertSame('github-trending', $candidates[0]->sourceSlug);
        $this->assertSame('2026-10-16 07:00:00', $candidates[0]->publishedAt?->toDateTimeString());
    }

    public function test_respects_max_items(): void
    {
        Http::fake([self::URL => Http::response((string) file_get_contents(base_path('tests/Fixtures/github-trending.html')))]);

        $this->assertCount(2, $this->fetch(maxItems: 2));
    }

    public function test_layout_change_throws(): void
    {
        Http::fake([self::URL => Http::response('<html><body><div class="new-layout">Trending</div></body></html>')]);

        $this->expectException(FetchException::class);

        $this->fetch();
    }

    public function test_http_error_throws(): void
    {
        Http::fake([self::URL => Http::response('rate limited', 429)]);

        $this->expectException(FetchException::class);

        $this->fetch();
    }

    /**
     * @return list<Candidate>
     */
    private function fetch(int $maxItems = 10): array
    {
        $source = new Source([
            'slug' => 'github-trending',
            'name' => 'GitHub Trending',
            'type' => SourceType::GithubTrending,
            'url' => self::URL,
            'tag' => 'devtools',
            'max_items' => $maxItems,
            'position' => 1,
        ]);

        return (new GithubTrendingFetcher)->fetch($source, CarbonImmutable::parse('2026-10-15 07:00:00'));
    }
}
