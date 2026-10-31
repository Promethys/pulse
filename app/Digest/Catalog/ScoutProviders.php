<?php

declare(strict_types=1);

namespace App\Digest\Catalog;

use App\Digest\Ai\AiConfigurationException;
use App\Digest\Ai\ProviderChain;
use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\Providers\SupportsWebSearch;

final class ScoutProviders
{
    /**
     * @return array<string, string>
     */
    public static function chain(): array
    {
        $chain = [];

        foreach (ProviderChain::fromConfig() as $provider => $model) {
            if (Ai::textProvider($provider) instanceof SupportsWebSearch) {
                $chain[$provider] = $model;
            }
        }

        if ($chain === []) {
            throw new AiConfigurationException('DIGEST_AI_PROVIDERS has no provider supporting web search');
        }

        return $chain;
    }
}
