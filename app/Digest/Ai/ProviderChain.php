<?php

declare(strict_types=1);

namespace App\Digest\Ai;

final class ProviderChain
{
    /**
     * DIGEST_AI_PROVIDERS ("gemini:<model>,anthropic:<model>") as the AI SDK's failover list.
     *
     * @return array<string, string> provider => model, in failover order
     *
     * @throws AiConfigurationException
     */
    public static function fromConfig(): array
    {
        $value = config('digest.ai_providers');
        $entries = array_filter(array_map('trim', explode(',', is_string($value) ? $value : '')));

        if ($entries === []) {
            throw new AiConfigurationException('DIGEST_AI_PROVIDERS is not set (e.g. "gemini:<model>,anthropic:<model>")');
        }

        $chain = [];
        foreach ($entries as $entry) {
            [$provider, $model] = array_pad(array_map('trim', explode(':', $entry, 2)), 2, '');

            if ($provider === '' || $model === '') {
                throw new AiConfigurationException("Invalid DIGEST_AI_PROVIDERS entry \"{$entry}\": expected provider:model");
            }
            if (! is_array(config("ai.providers.{$provider}"))) {
                throw new AiConfigurationException("Unknown AI provider \"{$provider}\": add it to config/ai.php");
            }
            if (isset($chain[$provider])) {
                throw new AiConfigurationException("AI provider \"{$provider}\" is listed twice in DIGEST_AI_PROVIDERS");
            }

            $chain[$provider] = $model;
        }

        return $chain;
    }
}
