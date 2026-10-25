<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class DiscordWebhookUrl implements ValidationRule
{
    public const PATTERN = '#\Ahttps://(?:discord\.com|discordapp\.com|ptb\.discord\.com|canary\.discord\.com)/api/(?:v[0-9]+/)?webhooks/[0-9]+/[A-Za-z0-9_-]+\z#';

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail('The :attribute must be a Discord webhook URL.');
        }
    }
}
