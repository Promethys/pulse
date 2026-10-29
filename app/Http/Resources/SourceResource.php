<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Source;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Source */
final class SourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'type' => $this->type->value,
            'url' => $this->url,
            'site_url' => $this->site_url,
            'is_custom' => $this->user_id !== null,
            'is_default' => $this->is_default,
            'enabled' => $this->enabled,
            'last_fetched_at' => $this->last_fetched_at?->toIso8601String(),
            'last_success_at' => $this->last_success_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'health' => $this->last_error !== null ? 'failing' : ($this->last_success_at?->greaterThanOrEqualTo(now()->subHours(48)) ? 'ok' : 'unknown'),
            'interests' => InterestResource::collection($this->whenLoaded('interests')),
        ];
    }
}
