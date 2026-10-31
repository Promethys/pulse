<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Enums\AiProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestAiKeyRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(AiProvider::class)],
            'model' => ['required', 'string', 'min:1', 'max:100'],
            'api_key' => ['required', 'string', 'min:1', 'max:500'],
        ];
    }
}
