<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LearningPost;
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
                    'items' => array_map(fn (PostItem $item) => (new PostItemResource($item))->resolve($request), $section['items']),
                ], $news['sections']),
            ],
            'learning' => $learning === null ? null : [
                'status' => $learning['status']->value,
                'tip' => $this->learningPost($learning['tip'], $request),
                'topic' => $this->learningPost($learning['topic'], $request),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function learningPost(?LearningPost $post, Request $request): ?array
    {
        return $post === null ? null : (new LearningPostResource($post))->resolve($request);
    }
}
