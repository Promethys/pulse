<?php

declare(strict_types=1);

namespace App\Digest\News;

use App\Digest\Ai\AiChainResolver;
use App\Digest\Ai\AiUnavailableException;
use App\Digest\Delivery\PostDeliverer;
use App\Digest\Discord\ForumPublisher;
use App\Digest\Rendering\NewsLine;
use App\Digest\Rendering\NewsRenderer;
use App\Digest\Sources\SourceCollector;
use App\Digest\Support\TagResolver;
use App\Enums\ItemStatus;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\Item;
use App\Models\Post;
use App\Models\PostItem;
use App\Models\Source;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/** One day's news post: collect → select (AI) → render → publish, resumable after any failure. */
class NewsPipeline
{
    public function __construct(
        private readonly SourceCollector $collector,
        private readonly NewsSelector $selector,
        private readonly NewsRenderer $renderer,
        private readonly ForumPublisher $publisher,
        private readonly TagResolver $tags,
        private readonly PostDeliverer $deliverer,
        private readonly CandidateQuery $candidates,
        private readonly AiChainResolver $ai,
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
            $this->fail($post, $e);

            throw $e;
        }

        return $post->refresh();
    }

    public function runFor(User $user, CarbonImmutable $date, bool $dryRun = false): Post
    {
        if ($dryRun) {
            DB::beginTransaction();
        }

        try {
            $post = Post::query()->firstOrCreate([
                'user_id' => $user->id,
                'date' => $date->toDateString(),
                'kind' => PostKind::News,
            ]);

            if ($post->rendered === null) {
                try {
                    DB::transaction(fn () => $this->generateFor($user, $post));
                } catch (Throwable $e) {
                    $this->fail($post, $e);

                    throw $e;
                }
            }

            if (! $dryRun) {
                $this->deliverer->deliver($post);
            }

            return $post;
        } finally {
            if ($dryRun) {
                DB::rollBack();
            }
        }
    }

    private function generateFor(User $user, Post $post): void
    {
        $this->collector->collectFor(collect([$user]));
        $candidates = $this->candidates->for($user, $this->sinceFor($user, $post));
        $model = 'none';
        $headlinesOnly = false;

        if ($candidates->isNotEmpty()) {
            try {
                $selection = $this->selector->selectFor($user, $post, $candidates, $this->ai->for($user));
                $model = $selection->model;
            } catch (AiUnavailableException) {
                $this->selector->headlinesFor($user, $post, $candidates);
                $headlinesOnly = true;
            }
        }

        $lines = $post->postItems()->with(['item', 'tags'])->get()->map(fn (PostItem $pick) => new NewsLine(
            $pick->title, $pick->summary, $pick->item->url, array_values(array_map(strval(...), $pick->tags->pluck('slug')->all())),
        ));
        $this->render($post, $lines, $this->collector->failuresFor($user), $model, $headlinesOnly);
    }

    private function sinceFor(User $user, Post $post): CarbonImmutable
    {
        $lastCreated = $user->posts()->where('kind', PostKind::News)
            ->where('id', '!=', $post->id)->latest('created_at')->value('created_at');
        $since = $lastCreated !== null ? CarbonImmutable::parse($lastCreated) : CarbonImmutable::now()->subDay();

        return $since->max(CarbonImmutable::now()->subDays(3));
    }

    private function fail(Post $post, Throwable $e): void
    {
        $post->update(['status' => PostStatus::Failed, 'attempts' => $post->attempts + 1, 'last_error' => $e->getMessage()]);
    }

    private function post(CarbonImmutable $today): Post
    {
        return Post::query()->firstOrCreate(['user_id' => null, 'date' => $today->toDateString(), 'kind' => PostKind::News]);
    }

    private function generate(Post $post): void
    {
        $collection = $this->collector->collect($this->since());

        /** @var Collection<int, Item> $candidates */
        $candidates = Item::query()->where('status', ItemStatus::Candidate)->whereNull('post_id')->get();

        $model = 'none';
        $headlinesOnly = false;

        if ($candidates->isEmpty()) {
            /** @var Collection<int, Item> $items */
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

        $defaultTags = Source::query()->pluck('tag', 'slug');
        $lines = $items->map(fn (Item $item) => new NewsLine(
            $item->title,
            $item->summary,
            $item->url,
            $item->tags->isNotEmpty() ? array_values(array_map(strval(...), $item->tags->pluck('slug')->all())) : [(string) ($defaultTags[$item->source_slug] ?? 'general')],
        ));
        $this->render($post, $lines, $collection->failures, $model, $headlinesOnly);
    }

    /**
     * @param  Collection<int, NewsLine>  $lines
     * @param  array<string, string>  $failures
     */
    private function render(Post $post, Collection $lines, array $failures, string $model, bool $headlinesOnly): void
    {
        $rendered = $this->renderer->render($post->date, $lines, $failures, $headlinesOnly);
        $post->update(['rendered' => $rendered->toArray(), 'model_used' => $model, 'status' => PostStatus::Generated, 'last_error' => null]);
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
