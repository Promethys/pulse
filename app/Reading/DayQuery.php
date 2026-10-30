<?php

declare(strict_types=1);

namespace App\Reading;

use App\Digest\Rendering\NewsSections;
use App\Enums\LearningKind;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostItem;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class DayQuery
{
    /**
     * @return list<string>
     */
    public function dates(User $user): array
    {
        return array_values(Post::query()->where('user_id', $user->id)
            ->select('date')->distinct()->orderByDesc('date')->get()
            ->map(fn (Post $post) => $post->date->toDateString())->all());
    }

    public function for(User $user, CarbonImmutable|string $date): ?Day
    {
        $date = $date instanceof CarbonImmutable ? $date->toDateString() : $date;
        $posts = Post::query()->where('user_id', $user->id)->where('date', $date)->get();

        if ($posts->isEmpty()) {
            return null;
        }

        $newsPost = $posts->first(fn (Post $post) => $post->kind === PostKind::News);
        $learningPost = $posts->first(fn (Post $post) => $post->kind === PostKind::Learning);
        $news = null;
        $learning = null;

        if ($newsPost !== null) {
            $items = $newsPost->postItems()->with([
                'item.sources' => fn ($query) => $query->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', $user->id)),
                'tags',
            ])->get()->each->setRelation('post', $newsPost);
            $news = [
                'status' => $newsPost->status,
                'sections' => $newsPost->status === PostStatus::Generated
                    ? NewsSections::group($items, fn (PostItem $item) => array_values($item->tags->map(fn (Tag $tag) => $tag->slug)->all()))
                    : [],
                'item_count' => $items->count(),
            ];
        }

        if ($learningPost !== null) {
            $entries = $learningPost->learningPosts()->with(['topic', 'tags'])->get();
            $learning = [
                'status' => $learningPost->status,
                'tip' => $entries->firstWhere('kind', LearningKind::Tip),
                'topic' => $entries->firstWhere('kind', LearningKind::Topic),
            ];
        }

        $previous = Post::query()->where('user_id', $user->id)->where('date', '<', $date)->orderByDesc('date')->first();
        $next = Post::query()->where('user_id', $user->id)->where('date', '>', $date)->orderBy('date')->first();

        return new Day($date, $news, $learning, $previous?->date->toDateString(), $next?->date->toDateString());
    }
}
