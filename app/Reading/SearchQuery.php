<?php

declare(strict_types=1);

namespace App\Reading;

use App\Models\Post;
use App\Models\PostItem;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class SearchQuery
{
    /**
     * @return LengthAwarePaginator<int, PostItem>
     */
    public function for(User $user, SearchFilters $filters, int $perPage = 30): LengthAwarePaginator
    {
        $query = PostItem::query()
            ->whereHas('post', function (Builder $query) use ($user, $filters) {
                $query->where('user_id', $user->id);

                if ($filters->from !== null) {
                    $query->where('date', '>=', $filters->from);
                }

                if ($filters->to !== null) {
                    $query->where('date', '<=', $filters->to);
                }
            });

        if ($filters->q !== null) {
            $query->whereRaw("search @@ websearch_to_tsquery('english', ?)", [$filters->q]);
        }

        if ($filters->tags !== []) {
            $query->whereHas('tags', fn (Builder $query) => $query->whereIn('slug', $filters->tags));
        }

        if ($filters->source !== null) {
            $query->whereHas('item.sources', fn (Builder $query) => $query->where('slug', $filters->source)
                ->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', $user->id)));
        }

        return $query->with([
            'post',
            'item.sources' => fn ($query) => $query->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', $user->id)),
            'tags',
        ])
            ->orderByDesc(Post::query()->select('date')->whereColumn('posts.id', 'post_items.post_id'))
            ->orderBy('position')->paginate($perPage);
    }

    /**
     * @return array{tags: list<Tag>, sources: list<Source>}
     */
    public function options(User $user): array
    {
        $items = PostItem::query()->select('id')
            ->whereHas('post', fn (Builder $query) => $query->where('user_id', $user->id));
        $tags = Tag::query()->whereIn('id', function ($query) use ($items) {
            $query->select('tag_id')->from('post_item_tag')->whereIn('post_item_id', $items);
        })->orderBy('name')->get();
        $sources = Source::query()
            ->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', $user->id))
            ->whereHas('items', fn (Builder $query) => $query->whereHas('postItems',
                fn (Builder $query) => $query->whereHas('post', fn (Builder $query) => $query->where('user_id', $user->id))))
            ->orderBy('name')->get();

        return ['tags' => array_values($tags->all()), 'sources' => array_values($sources->all())];
    }
}
