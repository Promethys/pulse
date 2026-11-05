<?php

declare(strict_types=1);

namespace App\Auth;

final class SocialProviders
{
    /**
     * @return list<string>
     */
    public function enabled(): array
    {
        return array_values(array_filter(['google', 'github'], $this->isEnabled(...)));
    }

    public function isEnabled(string $provider): bool
    {
        if (! in_array($provider, ['google', 'github'], true)) {
            return false;
        }

        foreach (['client_id', 'client_secret', 'redirect'] as $key) {
            $value = config("services.{$provider}.{$key}");

            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }
}
