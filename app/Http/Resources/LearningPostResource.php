<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LearningPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Phiki\Adapters\CommonMark\PhikiExtension;
use Phiki\Theme\Theme;

/** @mixin LearningPost */
final class LearningPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'date' => $this->whenLoaded('post', fn () => $this->post->date->toDateString()),
            'title' => $this->title,
            'body' => $this->body,
            'body_html' => Str::markdown($this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false], [
                new PhikiExtension(['light' => Theme::GithubLight, 'dark' => Theme::GithubDark]),
            ]),
            'category' => $this->topic?->category,
            'links' => $this->links ?? [],
            'tags' => $this->whenLoaded('tags', fn () => TagResource::collection($this->tags)->resolve($request)),
        ];
    }
}
