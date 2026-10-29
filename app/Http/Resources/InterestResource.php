<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Interest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Interest */
final class InterestResource extends JsonResource
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
            'category' => $this->category->value,
            'icon' => $this->icon,
            'position' => $this->position,
            'has_learning' => $this->has_learning,
            'status' => $this->status->value,
            'selected' => $this->whenHas('selected'),
        ];
    }
}
