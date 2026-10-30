<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReorderAiKeysRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'size:'.$this->user()->aiKeys()->count()],
            'ids.*' => ['required', 'integer', 'distinct', Rule::exists('ai_keys', 'id')->where('user_id', $this->user()->id)],
        ];
    }
}
