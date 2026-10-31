<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Enums\InterestStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChooseInterestsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'interests' => ['required', 'array', 'min:1'],
            'interests.*' => ['required', 'string', Rule::exists('interests', 'slug')->where('status', InterestStatus::Live->value)],
        ];
    }
}
