<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\LearningKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LearningRequest extends FormRequest
{
    protected $redirectRoute = 'learning.index';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['nullable', Rule::enum(LearningKind::class)],
            'category' => ['nullable', 'string', 'max:100'],
            'tag' => ['nullable', 'string', 'max:100'],
        ];
    }
}
