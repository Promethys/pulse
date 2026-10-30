<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PostItem;
use App\Models\Source;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PostItem */
final class PostItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $date = $this->whenLoaded('post', fn () => $this->post->date->toDateString());
        $ownerId = $this->post->user_id;
        $source = $this->item->sources->sortBy('id')->first(
            fn (Source $source) => $source->user_id === null || $source->user_id === $ownerId,
        );

        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'position' => $this->position,
            'url' => $this->item->url,
            'date' => $date,
            'source' => $source?->name,
            'tags' => $this->whenLoaded('tags', fn () => TagResource::collection($this->tags)->resolve($request)),
        ];
    }
}
