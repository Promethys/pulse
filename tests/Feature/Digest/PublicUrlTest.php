<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Support\PublicUrl;
use App\Digest\Support\UnsafeUrlException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    /** @param  list<string>  $ips */
    #[DataProvider('urls')]
    public function test_only_http_urls_with_exclusively_global_addresses_are_public(string $url, array $ips, bool $expected): void
    {
        $publicUrl = new PublicUrl(fn (string $host) => $ips);

        $this->assertSame($expected, $publicUrl->isPublic($url));
        Http::assertNothingSent();
    }

    /** @return array<string, array{string, list<string>, bool}> */
    public static function urls(): array
    {
        return [
            'public ipv4 dns' => ['https://public.test/feed', ['93.184.216.34'], true],
            'public ipv6 dns' => ['http://public.test/feed', ['2606:4700:4700::1111'], true],
            'public dual stack' => ['https://public.test/', ['93.184.216.34', '2606:4700:4700::1111'], true],
            'public ipv4 literal bypasses resolver' => ['https://93.184.216.34/feed', [], true],
            'public ipv6 literal bypasses resolver' => ['https://[2606:4700:4700::1111]/feed', [], true],
            'loopback literal' => ['http://127.0.0.1/', ['93.184.216.34'], false],
            'private literal' => ['http://10.0.0.5/', ['93.184.216.34'], false],
            'metadata literal' => ['http://169.254.169.254/latest/meta-data', ['93.184.216.34'], false],
            'ipv6 loopback literal' => ['http://[::1]/', ['93.184.216.34'], false],
            'ipv6 private literal' => ['http://[fd00::1]/', ['93.184.216.34'], false],
            'ipv6 link local literal' => ['http://[fe80::1]/', ['93.184.216.34'], false],
            'loopback dns' => ['https://internal.test/', ['127.0.0.2'], false],
            'private class a' => ['https://internal.test/', ['10.1.2.3'], false],
            'private class b' => ['https://internal.test/', ['172.16.0.1'], false],
            'private class c' => ['https://internal.test/', ['192.168.1.1'], false],
            'link local dns' => ['https://internal.test/', ['169.254.1.2'], false],
            'metadata dns' => ['https://internal.test/', ['169.254.169.254'], false],
            'unspecified ipv4' => ['https://internal.test/', ['0.0.0.0'], false],
            'shared address space' => ['https://internal.test/', ['100.64.0.1'], false],
            'ipv6 loopback dns' => ['https://internal.test/', ['::1'], false],
            'ipv6 unspecified' => ['https://internal.test/', ['::'], false],
            'ipv6 unique local fc' => ['https://internal.test/', ['fc00::1'], false],
            'ipv6 unique local fd' => ['https://internal.test/', ['fd12::1'], false],
            'ipv6 link local dns' => ['https://internal.test/', ['fe80::1'], false],
            'ipv4 mapped loopback' => ['https://internal.test/', ['::ffff:127.0.0.1'], false],
            'mixed public private ipv4' => ['https://mixed.test/', ['93.184.216.34', '10.0.0.5'], false],
            'mixed public private ipv6' => ['https://mixed.test/', ['2606:4700:4700::1111', 'fd00::1'], false],
            'mixed public ipv4 private ipv6' => ['https://mixed.test/', ['93.184.216.34', '::1'], false],
            'unresolved host' => ['https://unknown.test/', [], false],
            'invalid resolved address' => ['https://unknown.test/', ['not-an-ip'], false],
            'ftp' => ['ftp://public.test/feed', ['93.184.216.34'], false],
            'file' => ['file:///etc/passwd', ['93.184.216.34'], false],
            'javascript' => ['javascript:alert(1)', ['93.184.216.34'], false],
            'scheme relative' => ['//public.test/feed', ['93.184.216.34'], false],
            'no host' => ['https:///feed', ['93.184.216.34'], false],
            'relative path' => ['/feed', ['93.184.216.34'], false],
        ];
    }

    public function test_get_refuses_an_unsafe_initial_url_without_requesting_it(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        try {
            $this->publicUrl()->get(Http::timeout(5), 'http://127.0.0.1/admin');
            $this->fail('Expected UnsafeUrlException');
        } catch (UnsafeUrlException) {
            Http::assertNothingSent();
        }
    }

    public function test_get_checks_every_hop_before_following_a_redirect_to_private_addresses(): void
    {
        Http::fake([
            'https://public.test/start' => Http::response('', 302, ['Location' => 'https://public.test/next']),
            'https://public.test/next' => Http::response('', 307, ['Location' => 'http://169.254.169.254/latest/meta-data']),
            'http://169.254.169.254/*' => Http::response('secret', 200),
        ]);

        try {
            $this->publicUrl()->get(Http::timeout(5), 'https://public.test/start');
            $this->fail('Expected UnsafeUrlException');
        } catch (UnsafeUrlException) {
            Http::assertSentCount(2);
            Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '169.254.169.254'));
        }
    }

    #[DataProvider('relativeLocations')]
    public function test_get_resolves_relative_redirects_and_returns_the_final_response(string $location, string $target): void
    {
        Http::fake([
            'https://public.test/dir/start' => Http::response('', 302, ['Location' => $location]),
            $target => Http::response('feed body', 200, ['X-Final' => 'yes']),
        ]);

        $response = $this->publicUrl()->get(Http::timeout(5), 'https://public.test/dir/start', maxRedirects: 1);

        $this->assertSame(200, $response->status());
        $this->assertSame('feed body', $response->body());
        $this->assertSame('yes', $response->header('X-Final'));
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === $target);
        Http::assertSentCount(2);
    }

    /** @return array<string, array{string, string}> */
    public static function relativeLocations(): array
    {
        return [
            'root relative' => ['/feed', 'https://public.test/feed'],
            'path relative' => ['next', 'https://public.test/dir/next'],
            'parent relative' => ['../feed', 'https://public.test/feed'],
            'scheme relative' => ['//public.test/feed', 'https://public.test/feed'],
        ];
    }

    #[DataProvider('redirectLimits')]
    public function test_get_throws_when_the_redirect_limit_is_exceeded(?int $maxRedirects, int $requests): void
    {
        Http::fake(['https://public.test/*' => Http::response('', 302, ['Location' => '/loop'])]);

        try {
            if ($maxRedirects === null) {
                $this->publicUrl()->get(Http::timeout(5), 'https://public.test/start');
            } else {
                $this->publicUrl()->get(Http::timeout(5), 'https://public.test/start', $maxRedirects);
            }
            $this->fail('Expected UnsafeUrlException');
        } catch (UnsafeUrlException) {
            Http::assertSentCount($requests);
        }
    }

    /** @return array<string, array{int|null, int}> */
    public static function redirectLimits(): array
    {
        return ['default three redirects' => [null, 4], 'one redirect' => [1, 2], 'no redirects' => [0, 1]];
    }

    public function test_final_url_returns_the_original_url_when_there_is_no_redirect(): void
    {
        Http::fake(['https://public.test/feed' => Http::response('feed body')]);

        $this->assertSame('https://public.test/feed', $this->publicUrl()->finalUrl(Http::timeout(5), 'https://public.test/feed'));

        Http::assertSentCount(1);
    }

    #[DataProvider('relativeLocations')]
    public function test_final_url_resolves_relative_redirects(string $location, string $target): void
    {
        Http::fake([
            'https://public.test/dir/start' => Http::response('', 302, ['Location' => $location]),
            $target => Http::response('feed body'),
        ]);

        $this->assertSame($target, $this->publicUrl()->finalUrl(Http::timeout(5), 'https://public.test/dir/start', maxRedirects: 1));

        Http::assertSentCount(2);
    }

    public function test_final_url_follows_multiple_hops(): void
    {
        Http::fake([
            'https://public.test/start' => Http::response('', 301, ['Location' => '/next']),
            'https://public.test/next' => Http::response('', 307, ['Location' => '/feed']),
            'https://public.test/feed' => Http::response('feed body'),
        ]);

        $this->assertSame('https://public.test/feed', $this->publicUrl()->finalUrl(Http::timeout(5), 'https://public.test/start'));

        Http::assertSentCount(3);
    }

    public function test_final_url_refuses_a_private_initial_url_without_requesting_it(): void
    {
        Http::fake(['*' => Http::response('secret')]);

        try {
            $this->publicUrl()->finalUrl(Http::timeout(5), 'http://127.0.0.1/admin');
            $this->fail('Expected UnsafeUrlException');
        } catch (UnsafeUrlException) {
            Http::assertNothingSent();
        }
    }

    public function test_final_url_checks_every_hop_before_following_a_private_redirect(): void
    {
        Http::fake([
            'https://public.test/start' => Http::response('', 302, ['Location' => '/next']),
            'https://public.test/next' => Http::response('', 302, ['Location' => 'http://10.0.0.5/feed']),
            'http://10.0.0.5/feed' => Http::response('secret'),
        ]);

        try {
            $this->publicUrl()->finalUrl(Http::timeout(5), 'https://public.test/start');
            $this->fail('Expected UnsafeUrlException');
        } catch (UnsafeUrlException) {
            Http::assertSentCount(2);
            Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '10.0.0.5'));
        }
    }

    #[DataProvider('redirectLimits')]
    public function test_final_url_enforces_the_redirect_limit(?int $maxRedirects, int $requests): void
    {
        Http::fake(['https://public.test/*' => Http::response('', 302, ['Location' => '/loop'])]);

        try {
            if ($maxRedirects === null) {
                $this->publicUrl()->finalUrl(Http::timeout(5), 'https://public.test/start');
            } else {
                $this->publicUrl()->finalUrl(Http::timeout(5), 'https://public.test/start', $maxRedirects);
            }
            $this->fail('Expected UnsafeUrlException');
        } catch (UnsafeUrlException) {
            Http::assertSentCount($requests);
        }
    }

    public function test_final_url_refuses_a_redirect_without_a_location(): void
    {
        Http::fake(['https://public.test/start' => Http::response('', 302)]);

        $this->expectException(UnsafeUrlException::class);
        $this->expectExceptionMessage('Redirect has no location');

        $this->publicUrl()->finalUrl(Http::timeout(5), 'https://public.test/start');
    }

    private function publicUrl(): PublicUrl
    {
        return new PublicUrl(fn (string $host) => $host === 'public.test' ? ['93.184.216.34'] : []);
    }
}
