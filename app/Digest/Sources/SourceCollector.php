<?php

declare(strict_types=1);

namespace App\Digest\Sources;

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Throwable;

final class SourceCollector
{
    public function __construct(private readonly Container $container) {}

    public function collect(CarbonImmutable $since): CollectionResult
    {
        $stored = 0;
        $failures = [];

        foreach (Source::query()->enabled()->get() as $source) {
            $source->last_fetched_at = now();

            try {
                $stored += $this->store($this->fetch($source, $since));
                $source->last_success_at = now();
                $source->last_error = null;
            } catch (Throwable $e) {
                if (! $e instanceof FetchException) {
                    report($e);
                }
                $failures[$source->name] = $e->getMessage();
                $source->last_error = $e->getMessage();
            }

            $source->save();
        }

        return new CollectionResult($stored, $failures);
    }

    /**
     * @return list<Candidate>
     *
     * @throws FetchException
     */
    public function fetch(Source $source, CarbonImmutable $since): array
    {
        $fetcher = $this->container->make($source->type->fetcher());

        if (! $fetcher instanceof SourceFetcher) {
            throw new FetchException("No fetcher for source type {$source->type->value}");
        }

        return $fetcher->fetch($source, $since);
    }

    /**
     * @param  list<Candidate>  $candidates
     */
    private function store(array $candidates): int
    {
        $stored = 0;

        foreach ($candidates as $candidate) {
            $item = Item::query()->firstOrCreate(
                ['url_normalized' => UrlNormalizer::normalize($candidate->url)],
                [
                    'source_slug' => $candidate->sourceSlug,
                    'url' => $candidate->url,
                    'title' => Str::limit($candidate->title, 499, '…'),
                    'excerpt' => $candidate->excerpt,
                    'links' => $candidate->links,
                    'published_at' => $candidate->publishedAt,
                    'status' => ItemStatus::Candidate,
                ],
            );

            $stored += (int) $item->wasRecentlyCreated;
        }

        return $stored;
    }
}
