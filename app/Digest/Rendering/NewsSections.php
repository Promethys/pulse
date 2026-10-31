<?php

declare(strict_types=1);

namespace App\Digest\Rendering;

use App\Models\Tag;
use Closure;
use Illuminate\Support\Str;

final class NewsSections
{
    /**
     * @template T
     *
     * @param  iterable<T>  $entries
     * @param  Closure(T): list<string>  $tagSlugsOf
     * @return list<array{slug: string, name: string, items: list<T>}>
     */
    public static function group(iterable $entries, Closure $tagSlugsOf): array
    {
        $items = [];
        $tagsOf = [];
        $frequency = [];

        foreach ($entries as $entry) {
            $items[] = $entry;
            $slugs = $tagSlugsOf($entry) ?: ['general'];
            $tagsOf[] = $slugs;
            foreach ($slugs as $slug) {
                $frequency[$slug] = ($frequency[$slug] ?? 0) + 1;
            }
        }

        $names = Tag::query()->whereIn('slug', array_keys($frequency))->get()->keyBy('slug');
        $sections = [];
        foreach ($items as $index => $item) {
            $slugs = $tagsOf[$index];
            usort($slugs, fn (string $a, string $b) => [$frequency[$b], $a] <=> [$frequency[$a], $b]);
            $slug = $slugs[0];
            $tag = $names->get($slug);

            $sections[$slug] ??= ['slug' => $slug, 'name' => $tag === null ? Str::headline($slug) : $tag->name, 'items' => []];
            $sections[$slug]['items'][] = $item;
        }

        usort($sections, fn (array $a, array $b) => [count($b['items']), $a['name']] <=> [count($a['items']), $b['name']]);

        return $sections;
    }
}
