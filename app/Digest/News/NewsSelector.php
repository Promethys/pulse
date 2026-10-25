<?php

declare(strict_types=1);

namespace App\Digest\News;

use App\Ai\Agents\NewsCurator;
use App\Digest\Ai\AiUnavailableException;
use App\Digest\Sources\UrlNormalizer;
use App\Digest\Support\TagResolver;
use App\Models\Item;
use App\Models\Post;
use App\Models\PostItem;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Exceptions\FailoverableException;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

final class NewsSelector
{
    public function __construct(
        private readonly SelectionValidator $validator,
        private readonly TagResolver $tags,
    ) {}

    /**
     * @param  Collection<int, Item>  $candidates
     * @param  array<string, string>  $providers
     */
    public function selectFor(User $user, Post $post, Collection $candidates, array $providers): NewsSelection
    {
        $response = $this->ask($candidates, $user, $providers);
        $maxItems = $user->digestSettings->max_items ?? 20;
        $selected = $this->validator->validate(
            $response->toArray(),
            $candidates->keyBy('id'),
            $candidates->mapWithKeys(fn (Item $item) => [$item->id => $item->sources->first()->tag ?? 'general'])->all(),
            $maxItems,
        );

        $items = DB::transaction(function () use ($post, $selected, $candidates) {
            $post->postItems()->delete();
            /** @var Collection<int, PostItem> $items */
            $items = collect();
            $candidatesById = $candidates->keyBy('id');

            foreach ($selected as $pick) {
                $candidate = $candidatesById->get($pick->candidateId);
                if ($candidate === null) {
                    continue;
                }

                $item = $this->sharedItem($candidate, $pick);
                $items->push($this->storePostItem($post, $item, $pick->title, $pick->summary, $pick->tagSlugs, $items->count() + 1));
            }

            return $items;
        });

        return new NewsSelection($items, "{$response->meta->provider}:{$response->meta->model}");
    }

    /**
     * @param  Collection<int, Item>  $candidates
     * @return Collection<int, PostItem>
     */
    public function headlinesFor(User $user, Post $post, Collection $candidates): Collection
    {
        $headlines = $candidates->sortBy([
            fn (Item $a, Item $b) => ($a->sources->first()->position ?? PHP_INT_MAX) <=> ($b->sources->first()->position ?? PHP_INT_MAX),
            fn (Item $a, Item $b) => ($b->published_at?->getTimestamp() ?? 0) <=> ($a->published_at?->getTimestamp() ?? 0),
        ])->take($user->digestSettings->max_items ?? 20)->values();

        return DB::transaction(function () use ($post, $headlines) {
            $post->postItems()->delete();

            return $headlines->map(fn (Item $item, int $index) => $this->storePostItem(
                $post, $item, $item->title, null, [$item->sources->first()->tag ?? 'general'], $index + 1,
            ));
        });
    }

    private function sharedItem(Item $candidate, SelectedItem $pick): Item
    {
        $normalized = UrlNormalizer::normalize($pick->url);
        if ($normalized === $candidate->url_normalized) {
            return $candidate;
        }

        $source = $candidate->sources->firstOrFail();
        $item = Item::query()->firstOrCreate(['url_normalized' => $normalized], [
            'url' => $pick->url,
            'title' => $pick->title,
            'excerpt' => '',
            'links' => [$pick->url],
            'published_at' => $candidate->published_at,
        ]);

        DB::table('item_source')->insertOrIgnore([
            'item_id' => $item->id,
            'source_id' => $source->id,
            'first_seen_at' => now(),
        ]);

        return $item;
    }

    /**
     * @param  list<string>  $tagSlugs
     */
    private function storePostItem(Post $post, Item $item, string $title, ?string $summary, array $tagSlugs, int $position): PostItem
    {
        $pick = $post->postItems()->create([
            'item_id' => $item->id,
            'title' => $title,
            'summary' => $summary,
            'position' => $position,
        ]);
        $pick->tags()->sync($this->tags->resolve($tagSlugs)->pluck('id'));

        return $pick;
    }

    /**
     * @param  Collection<int, Item>  $candidates
     * @param  array<string, string>  $providers
     */
    private function ask(Collection $candidates, User $user, array $providers): StructuredAgentResponse
    {
        $agent = new NewsCurator(
            profile: (string) $user->digestSettings?->profile,
            existingTags: array_values(array_map(strval(...), Tag::query()->orderBy('slug')->pluck('slug')->all())),
            maxItems: $user->digestSettings->max_items ?? 20,
            interests: array_values(array_map(strval(...), $user->interests()->orderBy('position')->pluck('name')->all())),
        );

        try {
            $response = $agent->prompt(
                $this->prompt($candidates),
                provider: $providers,
                timeout: (int) config('digest.ai_timeout'),
            );
        } catch (Throwable $e) {
            if (! $e instanceof FailoverableException) {
                throw $e;
            }

            throw new AiUnavailableException($e->getMessage(), previous: $e);
        }

        if (! $response instanceof StructuredAgentResponse) {
            throw new AiUnavailableException('The AI provider returned no structured output');
        }

        return $response;
    }

    /**
     * @param  Collection<int, Item>  $candidates
     */
    private function prompt(Collection $candidates): string
    {
        $rows = $candidates->map(fn (Item $item) => [
            'id' => $item->id,
            'source' => $item->sources->first()?->slug,
            'title' => $item->title,
            'excerpt' => $item->excerpt,
            'links' => $item->links,
        ])->values()->all();

        return "Candidates:\n".json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
