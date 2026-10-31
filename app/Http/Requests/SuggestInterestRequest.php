<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\InterestCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SuggestInterestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'category' => ['required', Rule::enum(InterestCategory::class)],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
