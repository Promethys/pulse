<?php

declare(strict_types=1);

namespace App\Reading;

use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostItem;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ArchiveQuery
{
    /**
     * @return LengthAwarePaginator<int, array{date: string, item_count: int, tags: list<Tag>, news_status: ?PostStatus, learning_status: ?PostStatus}>
     */
    public function for(User $user, int $perPage = 30): LengthAwarePaginator
    {
        $dates = Post::query()->where('user_id', $user->id)
            ->select('date')->groupBy('date')->orderByDesc('date')->paginate($perPage);
        $posts = Post::query()->where('user_id', $user->id)
            ->whereIn('date', $dates->getCollection()->map(fn (Post $post) => $post->date->toDateString()))
            ->with(['postItems.tags'])->get()->groupBy(fn (Post $post) => $post->date->toDateString());

        return $dates->through(function (Post $date) use ($posts): array {
            $dayPosts = $posts->get($date->date->toDateString());
            $news = $dayPosts?->first(fn (Post $post) => $post->kind === PostKind::News);
            $learning = $dayPosts?->first(fn (Post $post) => $post->kind === PostKind::Learning);
            $tags = $news?->postItems->flatMap(fn (PostItem $item) => $item->tags)
                ->unique('id')->sortBy('name')->map(fn (Tag $tag) => $tag->withoutRelations())->values()->all() ?? [];

            return [
                'date' => $date->date->toDateString(),
                'item_count' => $news?->postItems->count() ?? 0,
                'tags' => array_values($tags),
                'news_status' => $news?->status,
                'learning_status' => $learning?->status,
            ];
        });
    }
}
