<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\LearningKind;
use App\Reading\SearchFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SearchRequest extends FormRequest
{
    protected $redirectRoute = 'search.index';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(['news', 'learning'])],
            'kind' => ['nullable', Rule::enum(LearningKind::class)],
            'category' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:200'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['required', 'string'],
            'source' => ['nullable', 'string'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['from' => 'start date', 'to' => 'end date'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['to.after_or_equal' => 'The end date must be on or after the start date.'];
    }

    public function filters(): SearchFilters
    {
        /** @var array{q?: string|null, tags?: array<int, string>, source?: string|null, from?: string|null, to?: string|null, type?: string|null, kind?: string|null, category?: string|null} $values */
        $values = $this->validated();

        return new SearchFilters(
            $values['q'] ?? null,
            array_values($values['tags'] ?? []),
            $values['source'] ?? null,
            $values['from'] ?? null,
            $values['to'] ?? null,
            $values['type'] ?? 'news',
            $values['kind'] ?? null,
            $values['category'] ?? null,
        );
    }
}
