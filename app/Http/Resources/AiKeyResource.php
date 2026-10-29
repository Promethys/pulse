<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AiKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiKey */
final class AiKeyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider->value,
            'model' => $this->model,
            'key_suffix' => $this->key_suffix,
            'position' => $this->position,
            'consented_at' => $this->consented_at->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
