<?php

declare(strict_types=1);

namespace App\Digest\Ai;

use App\Models\User;
use Laravel\Ai\Ai;

final class AiChainResolver
{
    /**
     * @return array<string, string>
     */
    public function for(User $user): array
    {
        $keys = $user->aiKeys()->get();

        if ($keys->isNotEmpty()) {
            $chain = [];

            foreach ($keys as $key) {
                $name = "user_{$user->id}_{$key->provider->value}";

                Ai::build([
                    ...(array) config("ai.providers.{$key->provider->value}"),
                    'name' => $name,
                    'key' => $key->api_key,
                ]);

                $chain[$name] = $key->model;
            }

            return $chain;
        }

        if ($user->uses_instance_ai) {
            return ProviderChain::fromConfig();
        }

        throw new AiNotConfiguredException('No AI keys configured for this user.');
    }
}
