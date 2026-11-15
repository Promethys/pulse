<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class PauseDigestsRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        $today = CarbonImmutable::now($this->user()->digestSettings()->sole()->timezone)->startOfDay();

        return [
            'until' => ['bail', 'nullable', 'date_format:Y-m-d', 'after_or_equal:'.$today->toDateString(), 'before_or_equal:'.$today->addDays(365)->toDateString()],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['until' => 'end date'];
    }
}
