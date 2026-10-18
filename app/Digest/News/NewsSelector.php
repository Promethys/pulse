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
use App\Models\Source;
use App\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
     *
     * @throws AiUnavailableException when no provider could answer (nothing is changed)
     */
    public function select(Post $post, Collection $candidates): NewsSelection
    {
        $candidatesById = $candidates->keyBy('id');
        $response = $this->ask($candidates);

        $selected = $this->validator->validate(
            $response->toArray(),
            $candidatesById,
            Source::query()->pluck('tag', 'slug')->all(),
        );

        $items = DB::transaction(fn () => $this->persist($post, $selected, $candidatesById));

        return new NewsSelection($items, "{$response->meta->provider}:{$response->meta->model}");
    }

    /**
     * @param  Collection<int, Item>  $candidates
     */
    private function ask(Collection $candidates): StructuredAgentResponse
    {
        $agent = new NewsCurator(
            profile: (string) config('digest.profile'),
            existingTags: array_values(array_map(strval(...), Tag::query()->orderBy('slug')->pluck('slug')->all())),
            maxItems: (int) config('digest.max_items'),
        );

        try {
            $response = $agent->prompt(
                $this->prompt($candidates),
                provider: ProviderChain::fromConfig(),
                timeout: (int) config('digest.ai_timeout'),
            );
        } catch (Throwable $e) {
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
            'source' => $item->source_slug,
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
