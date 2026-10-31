<?php

declare(strict_types=1);

namespace App\Digest\Catalog;

use App\Digest\Sources\FetchException;
use App\Digest\Sources\SourceCollector;
use App\Enums\SourceProposalKind;
use App\Enums\SourceProposalOrigin;
use App\Enums\SourceProposalStatus;
use App\Enums\SourceType;
use App\Models\Interest;
use App\Models\Source;
use App\Models\SourceCheck;
use App\Models\SourceProposal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CatalogChecker
{
    public function __construct(
        private readonly SourceCollector $collector,
        private readonly MovedFeedFinder $finder,
    ) {}

    public function check(Source $source): SourceCheck
    {
        $error = null;
        $suggestedUrl = null;

        try {
            $this->collector->fetch($source, CarbonImmutable::now()->subDays(30));
        } catch (Throwable $e) {
            if (! $e instanceof FetchException) {
                report($e);
            }

            $error = $e->getMessage();
            $suggestedUrl = $source->type === SourceType::Feed ? $this->finder->find($source) : null;
        }

        $previous = $source->checks()->orderByDesc('checked_at')->orderByDesc('id')->first();
        $check = $source->checks()->create([
            'checked_at' => now(),
            'ok' => $error === null,
            'error' => $error,
            'suggested_url' => $suggestedUrl,
        ]);

        if ($error === null) {
            return $check;
        }

        if ($suggestedUrl !== null) {
            SourceProposal::query()->firstOrCreate([
                'source_id' => $source->id,
                'url' => $suggestedUrl,
                'status' => SourceProposalStatus::Pending,
            ], [
                'kind' => SourceProposalKind::UrlChange,
                'origin' => SourceProposalOrigin::Check,
                'site_url' => $source->site_url,
                'name' => $source->name,
                'reason' => $error,
            ]);
        }

        $lastSuccess = $source->last_success_at ?? $source->created_at;
        $stale = $lastSuccess !== null
            && $lastSuccess->lessThan(now()->subDays(30))
            && $source->subscriptions()->where('enabled', true)->exists();

        if ($previous?->ok === false || $stale) {
            $source->update(['enabled' => false, 'retired_at' => now()]);
            Log::warning('Catalog source retired', ['source_id' => $source->id, 'error' => $error]);
            Interest::refreshStatuses();
        }

        return $check;
    }
}
