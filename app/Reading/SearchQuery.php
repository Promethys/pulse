<?php

declare(strict_types=1);

namespace App\Reading;

use App\Models\LearningPost;
use App\Models\LearningTopic;
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
        return $this->newsQuery($user, $filters)->with([
            'post',
            'item.sources' => fn ($query) => $query->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', $user->id)),
            'tags',
        ])
            ->orderByDesc(Post::query()->select('date')->whereColumn('posts.id', 'post_items.post_id'))
            ->orderBy('position')->paginate($perPage);
    }

    /**
     * @return LengthAwarePaginator<int, LearningPost>
     */
    public function learning(User $user, SearchFilters $filters, int $perPage = 30): LengthAwarePaginator
    {
        return $this->learningQuery($user, $filters)->with(['post', 'topic', 'tags'])
            ->orderByDesc(Post::query()->select('date')->whereColumn('posts.id', 'learning_posts.post_id'))
            ->orderBy('kind')->paginate($perPage);
    }

    /**
     * @return array{news: int, learning: int}
     */
    public function counts(User $user, SearchFilters $filters): array
    {
        return ['news' => $this->newsQuery($user, $filters)->count(), 'learning' => $this->learningQuery($user, $filters)->count()];
    }

    /**
     * @return Builder<PostItem>
     */
    private function newsQuery(User $user, SearchFilters $filters): Builder
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

        return $query;
    }

    /**
     * @return Builder<LearningPost>
     */
    private function learningQuery(User $user, SearchFilters $filters): Builder
    {
        $query = LearningPost::query()
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

        if ($filters->kind !== null) {
            $query->where('kind', $filters->kind);
        }

        if ($filters->category !== null) {
            $query->whereHas('topic', fn (Builder $query) => $query->where('category', $filters->category));
        }

        return $query;
    }

    /**
     * @return array{tags: list<Tag>, sources: list<Source>, categories: list<string>}
     */
    public function options(User $user): array
    {
        $items = PostItem::query()->select('id')
            ->whereHas('post', fn (Builder $query) => $query->where('user_id', $user->id));
        $learning = LearningPost::query()->whereHas('post', fn (Builder $query) => $query->where('user_id', $user->id));
        $tags = Tag::query()->where(function (Builder $query) use ($items, $learning) {
            $query->whereIn('id', function ($query) use ($items) {
                $query->select('tag_id')->from('post_item_tag')->whereIn('post_item_id', $items);
            })->orWhereIn('id', function ($query) use ($learning) {
                $query->select('tag_id')->from('learning_post_tag')->whereIn('learning_post_id', (clone $learning)->select('id'));
            });
        })->orderBy('name')->get();
        $categories = LearningTopic::query()->whereIn('id', (clone $learning)->select('learning_topic_id'))
            ->select('category')->distinct()->orderBy('category')->get()
            ->map(fn (LearningTopic $topic) => $topic->category)->values()->all();
        $sources = Source::query()
            ->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', $user->id))
            ->whereHas('items', fn (Builder $query) => $query->whereHas('postItems',
                fn (Builder $query) => $query->whereHas('post', fn (Builder $query) => $query->where('user_id', $user->id))))
            ->orderBy('name')->get();

        return ['tags' => array_values($tags->all()), 'sources' => array_values($sources->all()), 'categories' => array_values($categories)];
    }
}
