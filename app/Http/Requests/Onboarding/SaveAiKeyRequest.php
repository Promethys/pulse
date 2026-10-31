<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use Illuminate\Contracts\Validation\ValidationRule;

class SaveAiKeyRequest extends TestAiKeyRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'consent' => ['required', 'accepted']];
    }
}
