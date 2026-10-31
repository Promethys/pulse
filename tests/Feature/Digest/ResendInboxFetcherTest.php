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
            && $request->hasHeader('Authorization', 'Bearer test-key'));
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
        Http::fake([self::API.'*' => Http::response(['data' => [], 'has_more' => false])]);

        try {
            $this->fetch(credentials: []);
            $this->fail('Expected a FetchException for missing source credentials.');
        } catch (FetchException $exception) {
            $this->assertSame('This newsletter source has no Resend API key', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_instance_key_is_never_used_when_source_credentials_are_empty(): void
    {
        config(['services.resend.key' => 'instance-owner-key']);
        Http::fake([self::API.'*' => Http::response(['data' => [], 'has_more' => false])]);

        try {
            $this->fetch(credentials: []);
            $this->fail('Expected a FetchException instead of using the instance key.');
        } catch (FetchException $exception) {
            $this->assertSame('This newsletter source has no Resend API key', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_source_key_works_without_an_instance_key(): void
    {
        config(['services.resend.key' => null]);
        $this->fakeInbox();

        $this->assertCount(2, $this->fetch());

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-key'));
        Http::assertNotSent(fn (Request $request) => ! $request->hasHeader('Authorization', 'Bearer test-key'));
    }

    public function test_two_sources_authenticate_every_request_with_their_own_key(): void
    {
        Http::fake([
            self::API.'?limit=100' => Http::sequence()
                ->push(['data' => [['id' => 'first-email', 'created_at' => '2026-10-16T06:00:00Z']], 'has_more' => false])
                ->push(['data' => [['id' => 'second-email', 'created_at' => '2026-10-16T06:00:00Z']], 'has_more' => false]),
            self::API.'/first-email' => Http::response(['id' => 'first-email', 'subject' => 'First inbox', 'created_at' => '2026-10-16T06:00:00Z', 'text' => 'First body']),
            self::API.'/second-email' => Http::response(['id' => 'second-email', 'subject' => 'Second inbox', 'created_at' => '2026-10-16T06:00:00Z', 'text' => 'Second body']),
        ]);

        $first = $this->fetch(credentials: ['api_key' => 'first-user-key'], slug: 'first-newsletter');
        $second = $this->fetch(credentials: ['api_key' => 'second-user-key'], slug: 'second-newsletter');

        $requests = Http::recorded()->map(fn (array $pair) => $pair[0]);
        $this->assertCount(4, $requests);
        foreach ([0, 1] as $index) {
            $this->assertTrue($requests[$index]->hasHeader('Authorization', 'Bearer first-user-key'));
        }
        foreach ([2, 3] as $index) {
            $this->assertTrue($requests[$index]->hasHeader('Authorization', 'Bearer second-user-key'));
        }
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
     * @param  array<string, string>  $credentials
     * @return list<Candidate>
     */
    private function fetch(int $maxItems = 20, array $credentials = ['api_key' => 'test-key'], string $slug = 'newsletters'): array
    {
        $source = new Source([
            'slug' => $slug,
            'credentials' => $credentials,
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
