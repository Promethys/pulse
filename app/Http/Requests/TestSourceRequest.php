<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\PublicHttpUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TestSourceRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['feed', 'resend_inbox'])],
            'url' => ['required_if:type,feed', 'nullable', 'string', 'max:2000', new PublicHttpUrl],
            'api_key' => ['required_if:type,resend_inbox', 'nullable', 'string', 'max:200'],
        ];
    }
}
