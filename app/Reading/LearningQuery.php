<?php

declare(strict_types=1);

namespace App\Reading;

use App\Enums\LearningKind;
use App\Models\LearningPost;
use App\Models\LearningTopic;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class LearningQuery
{
    /**
     * @return LengthAwarePaginator<int, LearningPost>
     */
    public function list(User $user, ?LearningKind $kind = null, ?string $category = null, ?string $tagSlug = null, int $perPage = 30): LengthAwarePaginator
    {
        $query = $this->owned($user)->with(['post', 'topic', 'tags']);

        if ($kind !== null) {
            $query->where('kind', $kind);
        }
        if ($category !== null) {
            $query->whereHas('topic', fn (Builder $query) => $query->where('category', $category));
        }
        if ($tagSlug !== null) {
            $query->whereHas('tags', fn (Builder $query) => $query->where('slug', $tagSlug));
        }

        return $query->orderByDesc(Post::query()->select('date')->whereColumn('posts.id', 'learning_posts.post_id'))
            ->orderBy('kind')->paginate($perPage);
    }

    /**
     * @return array{categories: list<string>, tags: list<Tag>}
     */
    public function filters(User $user): array
    {
        $categories = LearningTopic::query()->whereIn('id', $this->owned($user)->select('learning_topic_id'))
            ->select('category')->distinct()->orderBy('category')->get()
            ->map(fn (LearningTopic $topic) => $topic->category)->values()->all();
        $tags = Tag::query()->whereIn('id', $this->owned($user)
            ->join('learning_post_tag', 'learning_post_tag.learning_post_id', '=', 'learning_posts.id')
            ->select('learning_post_tag.tag_id'))->orderBy('name')->get()->all();

        return ['categories' => array_values($categories), 'tags' => array_values($tags)];
    }

    public function find(User $user, int $id): ?LearningPost
    {
        return $this->owned($user)->with(['post', 'topic', 'tags'])->find($id);
    }

    /**
     * @return Builder<LearningPost>
     */
    private function owned(User $user): Builder
    {
        return LearningPost::query()->whereHas('post', fn (Builder $query) => $query->where('user_id', $user->id));
    }
}
