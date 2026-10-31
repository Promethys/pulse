<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DigestSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DigestSettings */
final class DigestSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'timezone' => $this->timezone,
            'profile' => $this->profile,
            'news_enabled' => $this->news_enabled,
            'news_time' => $this->news_time,
            'learning_enabled' => $this->learning_enabled,
            'learning_time' => $this->learning_time,
            'max_items' => $this->max_items,
            'onboarded_at' => $this->onboarded_at?->toIso8601String(),
            'learning_available' => $this->user->learningAvailable(),
        ];
    }
}
