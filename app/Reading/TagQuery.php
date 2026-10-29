<?php

declare(strict_types=1);

namespace App\Reading;

use App\Models\Post;
use App\Models\PostItem;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class TagQuery
{
    /**
     * @return array{tag: Tag, count: int, weeks: list<int>, items: LengthAwarePaginator<int, PostItem>}
     */
    public function for(User $user, Tag $tag, CarbonImmutable $today, int $perPage = 30): array
    {
        $query = PostItem::query()
            ->whereHas('post', fn (Builder $query) => $query->where('user_id', $user->id))
            ->whereHas('tags', fn (Builder $query) => $query->whereKey($tag->id));
        $count = (clone $query)->count();
        $counts = (clone $query)->join('posts', 'posts.id', '=', 'post_items.post_id')
            ->whereBetween('posts.date', [$today->subDays(83)->toDateString(), $today->toDateString()])
            ->selectRaw('posts.date, COUNT(*) as item_count')->groupBy('posts.date')->get();
        $weeks = array_fill(0, 12, 0);

        foreach ($counts as $row) {
            $daysAgo = (int) CarbonImmutable::parse((string) $row->getAttribute('date'))->diffInDays($today->startOfDay());
            $weeks[11 - intdiv($daysAgo, 7)] += (int) $row->getAttribute('item_count');
        }

        $items = $query->with([
            'post',
            'item.sources' => fn ($query) => $query->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', $user->id)),
            'tags',
        ])
            ->orderByDesc(Post::query()->select('date')->whereColumn('posts.id', 'post_items.post_id'))
            ->orderBy('position')->paginate($perPage);

        return ['tag' => $tag, 'count' => $count, 'weeks' => array_values($weeks), 'items' => $items];
    }
}
