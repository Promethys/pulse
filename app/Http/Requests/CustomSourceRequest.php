<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Source;
use App\Rules\PublicHttpUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CustomSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $source = $this->route('source');

        return ! $source instanceof Source || $this->user()->can('update', $source);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $source = $this->route('source');

        return [
            'type' => ['required', 'string', Rule::in($source instanceof Source ? [$source->type->value] : ['feed', 'resend_inbox'])],
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'url' => ['required_if:type,feed', 'nullable', 'string', 'max:2000', new PublicHttpUrl],
            'api_key' => [$source instanceof Source ? 'nullable' : 'required_if:type,resend_inbox', 'nullable', 'string', 'max:200'],
            'interests' => ['required', 'array', 'min:1'],
            'interests.*' => ['required', 'string', Rule::exists('interests', 'slug')],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->route('source') instanceof Source && Source::query()->where('user_id', $this->user()->id)->count() >= 30) {
                $validator->errors()->add($this->input('type') === 'feed' ? 'url' : 'name', __('You may save up to 30 custom sources.'));
            }
        }];
    }
}
