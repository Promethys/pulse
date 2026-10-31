<?php

declare(strict_types=1);

namespace Tests\Feature\Rules;

use App\Digest\Support\PublicUrl;
use App\Rules\PublicHttpUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicHttpUrlTest extends TestCase
{
    /** @param  list<string>  $ips */
    #[DataProvider('urls')]
    public function test_validates_public_http_urls_using_the_container_resolver(string $url, array $ips, bool $expected): void
    {
        Http::preventStrayRequests();
        $this->app->instance(PublicUrl::class, new PublicUrl(fn (string $host) => $ips));

        $validator = Validator::make(['url' => $url], ['url' => [new PublicHttpUrl]]);

        $this->assertSame($expected, $validator->passes());
        if (! $expected) {
            $this->assertSame(['The url must be a public http or https URL.'], $validator->errors()->get('url'));
        }
        Http::assertNothingSent();
    }

    /** @return array<string, array{string, list<string>, bool}> */
    public static function urls(): array
    {
        return [
            'https' => ['https://public.test/feed', ['93.184.216.34'], true],
            'http' => ['http://public.test/feed', ['93.184.216.34'], true],
            'ipv6 public' => ['https://public.test/feed', ['2606:4700:4700::1111'], true],
            'public literal' => ['https://93.184.216.34/feed', [], true],
            'private host' => ['https://internal.test/feed', ['10.0.0.5'], false],
            'mixed addresses' => ['https://mixed.test/feed', ['93.184.216.34', '192.168.0.1'], false],
            'ipv6 private' => ['https://internal.test/feed', ['fd00::1'], false],
            'loopback literal' => ['http://127.0.0.1/feed', ['93.184.216.34'], false],
            'metadata literal' => ['http://169.254.169.254/', ['93.184.216.34'], false],
            'ipv6 loopback literal' => ['http://[::1]/', ['93.184.216.34'], false],
            'unresolved' => ['https://unknown.test/feed', [], false],
            'ftp' => ['ftp://public.test/feed', ['93.184.216.34'], false],
            'file' => ['file:///etc/passwd', [], false],
            'javascript' => ['javascript:alert(1)', [], false],
            'no host' => ['https:///feed', [], false],
            'relative' => ['/feed', [], false],
        ];
    }
}
