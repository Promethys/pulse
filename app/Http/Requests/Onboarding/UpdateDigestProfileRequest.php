<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Concerns\DigestSettingsValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class UpdateDigestProfileRequest extends FormRequest
{
    use DigestSettingsValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return Arr::only($this->digestSettingsRules(), ['profile', 'timezone']);
    }
}
