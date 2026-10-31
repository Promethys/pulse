<?php

declare(strict_types=1);

namespace App\Digest\News;

use App\Digest\Ai\AiChainResolver;
use App\Digest\Ai\AiUnavailableException;
use App\Digest\Delivery\PostDeliverer;
use App\Digest\Rendering\NewsLine;
use App\Digest\Rendering\NewsRenderer;
use App\Digest\Sources\SourceCollector;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostItem;
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
        private readonly PostDeliverer $deliverer,
        private readonly CandidateQuery $candidates,
        private readonly AiChainResolver $ai,
    ) {}

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

    /**
     * @param  Collection<int, NewsLine>  $lines
     * @param  array<string, string>  $failures
     */
    private function render(Post $post, Collection $lines, array $failures, string $model, bool $headlinesOnly): void
    {
        $rendered = $this->renderer->render($post->date, $lines, $failures, $headlinesOnly);
        $post->update(['rendered' => $rendered->toArray(), 'model_used' => $model, 'status' => PostStatus::Generated, 'last_error' => null]);
    }
}
