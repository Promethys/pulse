<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Learning\LinkChecker;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LinkCheckerTest extends TestCase
{
    public function test_a_public_link_that_answers_is_alive(): void
    {
        Http::fake(['public.test/*' => Http::response('', 200)]);

        $this->assertTrue($this->checker()->alive('https://public.test/a'));
    }

    public function test_falls_back_to_get_when_head_is_refused(): void
    {
        Http::fake(['public.test/*' => Http::sequence()->push('', 405)->push('', 200)]);

        $this->assertTrue($this->checker()->alive('https://public.test/a'));
        Http::assertSent(fn (Request $r) => $r->method() === 'GET');
    }

    public function test_an_error_status_is_dead(): void
    {
        Http::fake(['public.test/*' => Http::response('', 404)]);

        $this->assertFalse($this->checker()->alive('https://public.test/a'));
    }

    public function test_non_http_links_are_dead(): void
    {
        Http::fake();

        $this->assertFalse($this->checker()->alive('file:///etc/passwd'));
        $this->assertFalse($this->checker()->alive('ftp://public.test/a'));
        Http::assertNothingSent();
    }

    public function test_private_and_loopback_addresses_are_never_requested(): void
    {
        Http::fake();

        $this->assertFalse($this->checker()->alive('http://127.0.0.1/admin'));
        $this->assertFalse($this->checker()->alive('http://[::1]/admin'));
        $this->assertFalse($this->checker()->alive('http://169.254.169.254/latest/meta-data'));
        $this->assertFalse($this->checker()->alive('http://internal.test/'));
        $this->assertFalse($this->checker()->alive('http://unknown.test/'));
        Http::assertNothingSent();
    }

    public function test_a_redirect_to_a_private_address_is_not_followed(): void
    {
        Http::fake([
            'public.test/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
            '169.254.169.254/*' => Http::response('', 200),
        ]);

        $this->assertFalse($this->checker()->alive('https://public.test/a'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '169.254.169.254'));
    }

    public function test_follows_public_redirects(): void
    {
        Http::fake([
            'public.test/old' => Http::response('', 301, ['Location' => '/new']),
            'public.test/new' => Http::response('', 200),
        ]);

        $this->assertTrue($this->checker()->alive('https://public.test/old'));
    }

    public function test_too_many_redirects_is_dead(): void
    {
        Http::fake(['public.test/*' => Http::response('', 302, ['Location' => '/loop'])]);

        $this->assertFalse($this->checker()->alive('https://public.test/a'));
    }

    private function checker(): LinkChecker
    {
        return new LinkChecker(fn (string $host) => match ($host) {
            'public.test' => ['93.184.216.34'],
            'internal.test' => ['93.184.216.34', '10.0.0.5'],
            default => [],
        });
    }
}
