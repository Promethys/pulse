<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SourceType;
use App\Models\Source;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SuggestSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $source = $this->route('source');

        return $source instanceof Source && $source->user_id === $this->user()->id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'interest' => ['required', 'string', Rule::exists('interests', 'slug')],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $source = $this->route('source');
            if ($source instanceof Source && $source->type !== SourceType::Feed) {
                $validator->errors()->add('reason', __('Only feeds can be suggested for the catalog.'));
            }
        }];
    }
}
