<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SocialProviderTimeoutsTest extends TestCase
{
    #[DataProvider('timeouts')]
    public function test_provider_requests_have_finite_timeouts(string $provider, string $option, int $seconds): void
    {
        $value = config("services.{$provider}.guzzle.{$option}");

        $this->assertIsNumeric($value);
        $this->assertGreaterThan(0, $value);
        $this->assertEquals($seconds, $value);
    }

    public static function timeouts(): array
    {
        return [
            'google timeout' => ['google', 'timeout', 10],
            'google connect timeout' => ['google', 'connect_timeout', 5],
            'github timeout' => ['github', 'timeout', 10],
            'github connect timeout' => ['github', 'connect_timeout', 5],
        ];
    }
}
