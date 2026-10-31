<?php

declare(strict_types=1);

namespace App\Digest\Catalog;

use App\Ai\Agents\SourceScout;
use App\Digest\Ai\ProviderChain;
use App\Enums\SourceProposalKind;
use App\Enums\SourceProposalOrigin;
use App\Enums\SourceProposalStatus;
use App\Models\Interest;
use App\Models\Source;
use App\Models\SourceProposal;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CatalogScout
{
    public const SEARCH_PROMPT = 'Find new feeds for this interest.';

    public const PLAIN_PROMPT = 'Suggest feeds you know for this interest.';

    public function __construct(
        private readonly ScoutResponseParser $parser,
        private readonly ProposalChecker $checker,
        private readonly MovedFeedFinder $feeds,
    ) {}

    public function scout(Interest $interest, int $max = 5): int
    {
        $interest->forceFill(['scouted_at' => now()])->save();

        if ($max <= 0) {
            return 0;
        }

        $domains = array_values(Source::query()->catalog()->get()
            ->map(fn (Source $source) => strtolower((string) parse_url((string) $source->url, PHP_URL_HOST)))
            ->filter()->unique()->all());
        $stored = 0;

        foreach ($this->proposals($interest, $max, $domains) as $proposal) {
            if ($stored >= $max) {
                break;
            }

            if (SourceProposal::query()->where('url', $proposal['url'])->exists()) {
                continue;
            }

            $check = $this->checker->check($proposal['url']);

            if ($check->failure === 'feed_parses') {
                $site = $proposal['site_url'] ?: parse_url($proposal['url'], PHP_URL_SCHEME).'://'.parse_url($proposal['url'], PHP_URL_HOST).'/';
                $feed = $this->feeds->advertisedFeed($site);

                if ($feed === null || SourceProposal::query()->where('url', $feed)->exists()) {
                    Log::info('Scout proposal failed a check', ['url' => $proposal['url'], 'failure' => $check->failure]);

                    continue;
                }

                $proposal['url'] = $feed;
                $check = $this->checker->check($feed);
            }

            if (! $check->passed) {
                Log::info('Scout proposal failed a check', ['url' => $proposal['url'], 'failure' => $check->failure]);

                continue;
            }

            SourceProposal::query()->create([
                ...$proposal,
                'origin' => SourceProposalOrigin::Scout,
                'kind' => SourceProposalKind::NewSource,
                'status' => SourceProposalStatus::Pending,
                'interest_id' => $interest->id,
                'checks' => $check->checks,
            ]);
            $stored++;
        }

        return $stored;
    }

    /**
     * @param  list<string>  $domains
     * @return list<array{name: string, url: string, site_url: ?string, reason: string}>
     */
    private function proposals(Interest $interest, int $max, array $domains): array
    {
        try {
            $proposals = $this->parser->parse((new SourceScout($interest->name, $max, $domains))->prompt(
                self::SEARCH_PROMPT,
                provider: ScoutProviders::chain(),
                timeout: 120,
            )->text);

            if ($proposals !== []) {
                return $proposals;
            }
        } catch (Throwable $e) {
            Log::info('Scout web search failed, retrying without it', ['interest' => $interest->slug, 'error' => $e->getMessage()]);
        }

        try {
            return $this->parser->parse((new SourceScout($interest->name, $max, $domains, webSearch: false))->prompt(
                self::PLAIN_PROMPT,
                provider: ProviderChain::fromConfig(),
                timeout: 120,
            )->text);
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }
}
