<?php

declare(strict_types=1);

namespace Tests\Unit\Digest;

use App\Digest\Ai\AiConfigurationException;
use App\Digest\Ai\ProviderChain;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProviderChainTest extends TestCase
{
    public function test_parses_the_ordered_chain(): void
    {
        config(['digest.ai_providers' => ' gemini:gemini-3.8-flash , anthropic:claude-haiku-5-5 ']);

        $this->assertSame(
            ['gemini' => 'gemini-3.8-flash', 'anthropic' => 'claude-haiku-5-5'],
            ProviderChain::fromConfig(),
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidChains(): array
    {
        return [
            'empty' => ['', 'DIGEST_AI_PROVIDERS is not set'],
            'missing model' => ['gemini', 'expected provider:model'],
            'unknown provider' => ['acme:big-model', 'Unknown AI provider "acme"'],
            'duplicate provider' => ['gemini:a,gemini:b', 'listed twice'],
        ];
    }

    #[DataProvider('invalidChains')]
    public function test_rejects_invalid_chains(string $value, string $message): void
    {
        config(['digest.ai_providers' => $value]);

        $this->expectException(AiConfigurationException::class);
        $this->expectExceptionMessage($message);

        ProviderChain::fromConfig();
    }
}
