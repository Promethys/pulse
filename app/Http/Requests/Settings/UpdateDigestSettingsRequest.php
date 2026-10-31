<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\DigestSettingsValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateDigestSettingsRequest extends FormRequest
{
    use DigestSettingsValidationRules;

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return $this->digestSettingsRules();
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->boolean('learning_enabled') && ! $this->user()->learningAvailable()) {
                $validator->errors()->add('learning_enabled', __('Available with a learning interest'));
            }
        }];
    }
}
