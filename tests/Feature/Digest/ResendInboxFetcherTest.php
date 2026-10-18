<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Sources\Candidate;
use App\Digest\Sources\FetchException;
use App\Digest\Sources\ResendInboxFetcher;
use App\Enums\SourceType;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResendInboxFetcherTest extends TestCase
{
    private const API = 'https://api.resend.com/emails/receiving';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.resend.key' => 're_test_key']);
    }

    public function test_one_candidate_per_email_with_links(): void
    {
        $this->fakeInbox();

        $candidates = $this->fetch();

        $this->assertSame(['Kilo Thursday roundup', 'Shifty Bits #212: Laravel 13 upgrade notes'], array_map(fn (Candidate $c) => $c->title, $candidates));

        $shift = $candidates[1];
        $this->assertSame('newsletters', $shift->sourceSlug);
        $this->assertSame('resend://email/email-new', $shift->url);
        $this->assertSame('2026-10-16 06:15:00', $shift->publishedAt?->utc()->toDateTimeString());
        $this->assertSame(
            ['https://laravelshift.com/blog/laravel-13-upgrade-notes', 'https://github.com/laravel/framework/pull/9999'],
            $shift->links,
        );
        $this->assertStringContainsString('Laravel 13 upgrade notes', $shift->excerpt);
        $this->assertStringNotContainsString('<', $shift->excerpt);
    }

    public function test_prefers_the_text_body_and_finds_bare_links(): void
    {
        $this->fakeInbox();

        $kilo = $this->fetch()[0];

        $this->assertSame('Roundup: new orchestrator mode https://blog.kilo.ai/p/orchestrator-mode . Read it.', $kilo->excerpt);
        $this->assertSame(['https://blog.kilo.ai/p/orchestrator-mode'], $kilo->links);
    }

    public function test_skips_emails_older_than_since(): void
    {
        $this->fakeInbox();

        $this->fetch();

        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/email-old'));
    }

    public function test_follows_pagination_and_authenticates(): void
    {
        $this->fakeInbox();

        $this->fetch();

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'after=email-old')
            && $request->hasHeader('Authorization', 'Bearer re_test_key'));
    }

    public function test_respects_max_items(): void
    {
        $this->fakeInbox();

        $candidates = $this->fetch(maxItems: 1);

        $this->assertCount(1, $candidates);
        $this->assertSame('Kilo Thursday roundup', $candidates[0]->title);
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/email-new'));
    }

    public function test_missing_api_key_throws(): void
    {
        config(['services.resend.key' => null]);

        $this->expectException(FetchException::class);
        $this->expectExceptionMessage('RESEND_API_KEY');

        $this->fetch();
    }

    public function test_http_error_throws(): void
    {
        Http::fake([self::API.'*' => Http::response(['message' => 'Unauthorized'], 401)]);

        $this->expectException(FetchException::class);
        $this->expectExceptionMessage('HTTP 401');

        $this->fetch();
    }

    private function fakeInbox(): void
    {
        $fixture = fn (string $name) => Http::response((string) file_get_contents(base_path("tests/Fixtures/resend/{$name}.json")));

        Http::fake([
            self::API.'/email-new' => $fixture('email-new'),
            self::API.'/email-page-2' => $fixture('email-page-2'),
            self::API.'?limit=100&after=email-old' => $fixture('list-page-2'),
            self::API.'?limit=100' => $fixture('list-page-1'),
        ]);
    }

    /**
     * @return list<Candidate>
     */
    private function fetch(int $maxItems = 20): array
    {
        $source = new Source([
            'slug' => 'newsletters',
            'name' => 'Newsletters',
            'type' => SourceType::ResendInbox,
            'url' => null,
            'tag' => 'devtools',
            'max_items' => $maxItems,
            'position' => 1,
        ]);

        return (new ResendInboxFetcher)->fetch($source, CarbonImmutable::parse('2026-10-15 07:00:00', 'UTC'));
    }
}
