<?php

namespace App\Concerns;

trait DigestSettingsValidationRules
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function digestSettingsRules(): array
    {
        return [
            'timezone' => ['required', 'string', 'timezone:all'],
            'profile' => ['nullable', 'string', 'max:2000'],
            'news_enabled' => ['required', 'boolean'],
            'news_time' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):(00|15|30|45)$/'],
            'learning_enabled' => ['required', 'boolean'],
            'learning_time' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):(00|15|30|45)$/'],
            'max_items' => ['required', 'integer', 'between:1,20'],
        ];
    }
}
