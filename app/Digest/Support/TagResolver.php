<?php

declare(strict_types=1);

namespace App\Digest\Support;

use App\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class TagResolver
{
    /**
     * Existing tags by slug, creating the missing ones.
     *
     * @param  list<string>  $slugs
     * @return Collection<int, Tag>
     */
    public function resolve(array $slugs): Collection
    {
        $slugs = array_values(array_unique(array_filter(array_map(fn (string $slug) => Str::slug($slug), $slugs))));

        return collect($slugs)->map(fn (string $slug) => Tag::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => Tag::displayName($slug)],
        ));
    }
}
