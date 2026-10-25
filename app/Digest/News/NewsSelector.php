<?php

declare(strict_types=1);

namespace App\Digest\News;

use App\Ai\Agents\NewsCurator;
use App\Digest\Ai\AiUnavailableException;
use App\Digest\Ai\ProviderChain;
use App\Digest\Sources\UrlNormalizer;
use App\Digest\Support\TagResolver;
use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\Post;
use App\Models\PostItem;
use App\Models\Source;
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
     * @return NewsSelection<Item>
     *
     * @throws AiUnavailableException when no provider could answer (nothing is changed)
     */
    public function select(Post $post, Collection $candidates): NewsSelection
    {
        $candidatesById = $candidates->keyBy('id');
        $response = $this->ask($candidates);

        $sourceTags = Source::query()->pluck('tag', 'slug');
        $selected = $this->validator->validate(
            $response->toArray(),
            $candidatesById,
            $candidates->mapWithKeys(fn (Item $item) => [$item->id => (string) ($sourceTags[$item->source_slug] ?? 'general')])->all(),
        );

        $items = DB::transaction(fn () => $this->persist($post, $selected, $candidatesById));

        return new NewsSelection($items, "{$response->meta->provider}:{$response->meta->model}");
    }

    /**
     * @param  Collection<int, Item>  $candidates
     * @param  array<string, string>  $providers
     * @return NewsSelection<PostItem>
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
            'source_slug' => $source->slug,
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
     * @param  array<string, string>|null  $providers
     */
    private function ask(Collection $candidates, ?User $user = null, ?array $providers = null): StructuredAgentResponse
    {
        $agent = new NewsCurator(
            profile: $user !== null ? (string) $user->digestSettings?->profile : (string) config('digest.profile'),
            existingTags: array_values(array_map(strval(...), Tag::query()->orderBy('slug')->pluck('slug')->all())),
            maxItems: $user !== null ? ($user->digestSettings->max_items ?? 20) : (int) config('digest.max_items'),
            interests: $user !== null ? array_values(array_map(strval(...), $user->interests()->orderBy('position')->pluck('name')->all())) : [],
        );

        try {
            $response = $agent->prompt(
                $this->prompt($candidates, $user !== null),
                provider: $providers ?? ProviderChain::fromConfig(),
                timeout: (int) config('digest.ai_timeout'),
            );
        } catch (Throwable $e) {
            if ($user !== null && ! $e instanceof FailoverableException) {
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
    private function prompt(Collection $candidates, bool $forUser = false): string
    {
        $rows = $candidates->map(fn (Item $item) => [
            'id' => $item->id,
            'source' => $forUser ? $item->sources->first()?->slug : $item->source_slug,
            'title' => $item->title,
            'excerpt' => $item->excerpt,
            'links' => $item->links,
        ])->values()->all();

        return "Candidates:\n".json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  list<SelectedItem>  $selected
     * @param  Collection<int, Item>  $candidatesById
     * @return Collection<int, Item>
     */
    private function persist(Post $post, array $selected, Collection $candidatesById): Collection
    {
        $items = collect();

        foreach ($selected as $pick) {
            $candidate = $candidatesById->get($pick->candidateId);
            if ($candidate === null) {
                continue;
            }

            $item = UrlNormalizer::normalize($pick->url) === $candidate->url_normalized
                ? $candidate
                : Item::query()->firstOrNew(['url_normalized' => UrlNormalizer::normalize($pick->url)], [
                    'source_slug' => $candidate->source_slug,
                    'url' => $pick->url,
                    'excerpt' => '',
                    'links' => [$pick->url],
                    'published_at' => $candidate->published_at,
                ]);

            $item->fill([
                'title' => $pick->title,
                'summary' => $pick->summary,
                'status' => ItemStatus::Selected,
                'post_id' => $post->id,
            ])->save();

            $item->tags()->sync($this->tags->resolve($pick->tagSlugs)->pluck('id'));
            $items->push($item);
        }

        $selectedIds = $items->pluck('id');
        $candidatesById->reject(fn (Item $item) => $selectedIds->contains($item->id))
            ->each(fn (Item $item) => $item->update(['status' => ItemStatus::Skipped]));

        return $items;
    }
}
