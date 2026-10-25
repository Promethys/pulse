<?php

declare(strict_types=1);

namespace App\Digest\Sources;

use App\Models\Item;
use App\Models\Source;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class SourceCollector
{
    public function __construct(private readonly Container $container) {}

    /**
     * @param  Collection<int, User>  $users
     */
    public function collectFor(Collection $users): CollectionResult
    {
        $userIds = $users->pluck('id');
        $sources = Source::query()->enabled()
            ->whereHas('subscriptions', fn ($query) => $query->where('enabled', true)->whereIn('user_id', $userIds))
            ->where(fn ($query) => $query->whereNull('last_success_at')->orWhere('last_success_at', '<=', now()->subMinutes(25)))
            ->get();

        return $this->collectSources($sources);
    }

    /**
     * @return array<string, string>
     */
    public function failuresFor(User $user): array
    {
        $sources = Source::query()->enabled()
            ->whereNotNull('last_error')
            ->whereHas('subscriptions', fn ($query) => $query->where('enabled', true)->where('user_id', $user->id))
            ->get();
        $failures = [];

        foreach ($sources as $source) {
            if ($source->last_error !== null) {
                $failures[$source->name] = $source->last_error;
            }
        }

        return $failures;
    }

    /**
     * @param  Collection<int, Source>  $sources
     */
    private function collectSources(Collection $sources): CollectionResult
    {
        $stored = 0;
        $failures = [];

        foreach ($sources as $source) {
            $source->last_fetched_at = now();

            try {
                $sourceSince = $source->last_success_at?->subHour() ?? CarbonImmutable::now()->subDay();
                $stored += $this->store($source, $this->fetch($source, $sourceSince));
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
    private function store(Source $source, array $candidates): int
    {
        $stored = 0;

        foreach ($candidates as $candidate) {
            $item = Item::query()->firstOrCreate(
                ['url_normalized' => UrlNormalizer::normalize($candidate->url)],
                [
                    'url' => $candidate->url,
                    'title' => Str::limit($candidate->title, 499, '…'),
                    'excerpt' => $candidate->excerpt,
                    'links' => $candidate->links,
                    'published_at' => $candidate->publishedAt,
                ],
            );

            DB::table('item_source')->insertOrIgnore([
                'item_id' => $item->id,
                'source_id' => $source->id,
                'first_seen_at' => now(),
            ]);

            $stored += (int) $item->wasRecentlyCreated;
        }

        return $stored;
    }
}
