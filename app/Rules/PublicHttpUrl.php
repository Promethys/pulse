<?php

declare(strict_types=1);

namespace App\Rules;

use App\Digest\Support\PublicUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class PublicHttpUrl implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! app(PublicUrl::class)->isPublic($value)) {
            $fail('The :attribute must be a public http or https URL.');
        }
    }
}
