<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Ai\AiConfigurationException;
use App\Digest\Catalog\ScoutProviders;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScoutProvidersTest extends TestCase
{
    public function test_filters_by_provider_capability_preserving_models_and_order(): void
    {
        config([
            'ai.providers.search_backup' => ['driver' => 'gemini', 'key' => 'fake-key'],
            'ai.providers.search_primary' => ['driver' => 'gemini', 'key' => 'fake-key'],
            'digest.ai_providers' => 'zai:unsupported,search_backup:backup-model,gemini:main-model,search_primary:last-model',
        ]);

        $this->assertSame([
            'search_backup' => 'backup-model',
            'gemini' => 'main-model',
            'search_primary' => 'last-model',
        ], ScoutProviders::chain());
    }

    #[DataProvider('unusableChains')]
    public function test_rejects_an_empty_or_invalid_search_chain(string $chain): void
    {
        config(['digest.ai_providers' => $chain]);

        $this->expectException(AiConfigurationException::class);

        ScoutProviders::chain();
    }

    /** @return array<string, array{string}> */
    public static function unusableChains(): array
    {
        return [
            'no web search provider' => ['zai:glm-test'],
            'empty configuration' => [''],
            'missing model' => ['gemini'],
            'unknown provider' => ['unknown:model'],
            'duplicate provider' => ['gemini:first,gemini:second'],
        ];
    }
}
