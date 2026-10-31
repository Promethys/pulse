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
}
