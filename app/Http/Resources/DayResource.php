<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PostItem;
use App\Reading\Day;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Day */
final class DayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $news = $this->news;
        $learning = $this->learning;

        if ($news !== null) {
            /** @var list<PostItem> $items */
            $items = [];
            foreach ($news['sections'] as $section) {
                array_push($items, ...$section['items']);
            }
            (new Collection($items))->loadMissing('post');
        }

        return [
            'date' => $this->date,
            'previous_date' => $this->previous_date,
            'next_date' => $this->next_date,
            'news' => $news === null ? null : [
                'status' => $news['status']->value,
                'item_count' => $news['item_count'],
                'sections' => array_map(fn (array $section) => [
                    'slug' => $section['slug'],
                    'name' => $section['name'],
                    'items' => PostItemResource::collection($section['items']),
                ], $news['sections']),
            ],
            'learning' => $learning === null ? null : [
                'status' => $learning['status']->value,
                'tip' => $learning['tip'] === null ? null : new LearningPostResource($learning['tip']),
                'topic' => $learning['topic'] === null ? null : new LearningPostResource($learning['topic']),
            ],
        ];
    }
}
