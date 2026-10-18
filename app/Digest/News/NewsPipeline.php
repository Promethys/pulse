<?php

declare(strict_types=1);

namespace App\Digest\News;

use App\Digest\Ai\AiUnavailableException;
use App\Digest\Discord\ForumPublisher;
use App\Digest\Rendering\NewsRenderer;
use App\Digest\Sources\SourceCollector;
use App\Digest\Support\TagResolver;
use App\Enums\ItemStatus;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\Item;
use App\Models\Post;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/** One day's news post: collect → select (AI) → render → publish, resumable after any failure. */
final class NewsPipeline
{
    public function __construct(
        private readonly SourceCollector $collector,
        private readonly NewsSelector $selector,
        private readonly NewsRenderer $renderer,
        private readonly ForumPublisher $publisher,
        private readonly TagResolver $tags,
    ) {}

    public function run(CarbonImmutable $today, bool $dryRun = false): Post
    {
        if ($dryRun) {
            DB::beginTransaction();

            try {
                $post = $this->post($today);
                if ($post->rendered === null) {
                    $this->generate($post);
                }

                return $post;
            } finally {
                DB::rollBack();
            }
        }

        $post = $this->post($today);

        if ($post->status === PostStatus::Posted) {
            return $post;
        }

        try {
            if ($post->rendered === null) {
                $this->generate($post);
            }

            $this->publisher->publish($post, (string) config('services.discord.news_forum'));
        } catch (Throwable $e) {
            $post->update(['status' => PostStatus::Failed, 'attempts' => $post->attempts + 1, 'last_error' => $e->getMessage()]);

            throw $e;
        }

        return $post->refresh();
    }

    private function post(CarbonImmutable $today): Post
    {
        return Post::query()->firstOrCreate(['date' => $today->toDateString(), 'kind' => PostKind::News]);
    }

    private function generate(Post $post): void
    {
        $collection = $this->collector->collect($this->since());

        /** @var Collection<int, Item> $candidates */
        $candidates = Item::query()->where('status', ItemStatus::Candidate)->whereNull('post_id')->get();

        $model = 'none';
        $headlinesOnly = false;

        if ($candidates->isEmpty()) {
            $items = collect();
        } else {
            try {
                $selection = $this->selector->select($post, $candidates);
                $items = $selection->items;
                $model = $selection->model;
            } catch (AiUnavailableException) {
                $items = $this->keepHeadlines($post, $candidates);
                $headlinesOnly = true;
            }
        }

        $rendered = $this->renderer->render(CarbonImmutable::parse($post->date), $items, $collection->failures, $headlinesOnly);

        $post->update(['rendered' => $rendered->toArray(), 'model_used' => $model, 'status' => PostStatus::Generated]);
    }

    /**
     * No AI: the chosen headlines are stored as this post's items (tagged with their source's default tag), the rest skipped.
     *
     * @param  Collection<int, Item>  $candidates
     * @return Collection<int, Item>
     */
    private function keepHeadlines(Post $post, Collection $candidates): Collection
    {
        $headlines = $this->renderer->headlines($candidates);
        $defaultTags = Source::query()->pluck('tag', 'slug');

        DB::transaction(function () use ($post, $candidates, $headlines, $defaultTags) {
            foreach ($headlines as $item) {
                $item->update(['status' => ItemStatus::Selected, 'post_id' => $post->id]);
                $item->tags()->sync($this->tags->resolve([(string) ($defaultTags[$item->source_slug] ?? 'general')])->pluck('id'));
            }

            $candidates->diff($headlines)->each(fn (Item $item) => $item->update(['status' => ItemStatus::Skipped]));
        });

        return $headlines->each(fn (Item $item) => $item->load('tags'));
    }

    private function since(): CarbonImmutable
    {
        $lastPosted = Post::query()
            ->where('kind', PostKind::News)
            ->where('status', PostStatus::Posted)
            ->whereNotNull('posted_at')
            ->latest('posted_at')
            ->value('posted_at');

        return $lastPosted !== null ? CarbonImmutable::parse($lastPosted) : CarbonImmutable::now()->subDay();
    }
}
