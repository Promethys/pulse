<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Reading\SearchFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class SearchRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['required', 'string'],
            'source' => ['nullable', 'string'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
        ];
    }

    public function filters(): SearchFilters
    {
        return new SearchFilters(
            $this->validated('q'),
            array_values($this->validated('tags', [])),
            $this->validated('source'),
            $this->validated('from'),
            $this->validated('to'),
        );
    }
}
