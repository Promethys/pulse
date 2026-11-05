<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\SocialProviders;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SocialProvidersTest extends TestCase
{
    #[DataProvider('providerConfigurations')]
    public function test_requires_all_three_credentials(string $provider, array $credentials, bool $enabled): void
    {
        config(['services.google' => [], 'services.github' => [], "services.{$provider}" => $credentials]);

        $providers = app(SocialProviders::class);

        $this->assertSame($enabled, $providers->isEnabled($provider));
        $this->assertSame($enabled ? [$provider] : [], $providers->enabled());
    }

    public static function providerConfigurations(): array
    {
        $cases = [];

        foreach (['google', 'github'] as $provider) {
            $credentials = ['client_id' => 'test-client', 'client_secret' => 'test-secret', 'redirect' => "https://example.com/auth/{$provider}/callback"];
            $cases["{$provider} complete"] = [$provider, $credentials, true];
            $cases["{$provider} absent"] = [$provider, [], false];

            foreach (array_keys($credentials) as $key) {
                foreach ([null, ''] as $value) {
                    $cases["{$provider} {$key} ".($value === null ? 'null' : 'empty')] = [$provider, array_replace($credentials, [$key => $value]), false];
                }
            }
        }

        return $cases;
    }

    public function test_lists_only_supported_providers_even_when_other_services_have_credentials(): void
    {
        $credentials = ['client_id' => 'test-client', 'client_secret' => 'test-secret', 'redirect' => 'https://example.com/callback'];
        config(['services.google' => $credentials, 'services.github' => $credentials, 'services.other' => $credentials]);

        $providers = app(SocialProviders::class);

        $this->assertSame(['google', 'github'], $providers->enabled());
        $this->assertFalse($providers->isEnabled('other'));
    }
}
