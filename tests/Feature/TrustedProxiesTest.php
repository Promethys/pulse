<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    public function test_it_uses_the_forwarded_scheme_host_and_client_ip(): void
    {
        Route::get('/proxy-check', fn (Request $request) => [
            'secure' => $request->isSecure(),
            'ip' => $request->ip(),
            'url' => url('/build/app.js'),
        ]);

        $this->get('/proxy-check', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'pulse.example.com',
            'X-Forwarded-For' => '203.0.113.9',
        ])->assertExactJson([
            'secure' => true,
            'ip' => '203.0.113.9',
            'url' => 'https://pulse.example.com/build/app.js',
        ]);
    }

    public function test_it_only_trusts_the_direct_proxy_by_default(): void
    {
        $this->assertSame('198.51.100.7', $this->clientIp('6.6.6.6, 198.51.100.7'));
        $this->assertSame('173.245.48.1', $this->clientIp('203.0.113.9, 173.245.48.1'));
    }

    public function test_extra_ranges_find_the_visitor_behind_an_edge_network(): void
    {
        config(['trustedproxy.proxies' => ['REMOTE_ADDR', '173.245.48.0/20', '2606:4700::/32']]);

        $this->assertSame('203.0.113.9', $this->clientIp('203.0.113.9, 173.245.48.1'));
        $this->assertSame('2001:db8::1', $this->clientIp('2001:db8::1, 2606:4700::1'));
        $this->assertSame('198.51.100.7', $this->clientIp('6.6.6.6, 198.51.100.7'));
    }

    public function test_forwarded_headers_can_be_ignored_when_no_proxy_is_in_front(): void
    {
        config(['trustedproxy.proxies' => []]);

        $this->assertSame('127.0.0.1', $this->clientIp('6.6.6.6'));
    }

    public function test_the_setting_reads_a_comma_separated_list(): void
    {
        $this->assertSame(['REMOTE_ADDR'], $this->parsedSetting(null));
        $this->assertSame(['REMOTE_ADDR', '10.0.0.0/8'], $this->parsedSetting('REMOTE_ADDR, 10.0.0.0/8'));
        $this->assertSame([], $this->parsedSetting('none'));
    }

    /**
     * @return array<int, string>
     */
    private function parsedSetting(?string $value): array
    {
        $_ENV['TRUSTED_PROXIES'] = $_SERVER['TRUSTED_PROXIES'] = $value;

        if ($value === null) {
            unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
        }

        $config = require base_path('config/trustedproxy.php');
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);

        return $config['proxies'];
    }

    private function clientIp(string $forwardedFor): mixed
    {
        Route::get('/ip-check', fn (Request $request) => ['ip' => $request->ip()]);

        return $this->get('/ip-check', ['X-Forwarded-For' => $forwardedFor])->json('ip');
    }
}
