<?php

declare(strict_types=1);

namespace App\Digest\Catalog;

use App\Ai\Agents\SourceScout;
use App\Digest\Ai\ProviderChain;
use App\Enums\SourceProposalOrigin;
use App\Models\Interest;
use App\Models\Source;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CatalogScout
{
    public const SEARCH_PROMPT = 'Find new feeds for this interest.';

    public const PLAIN_PROMPT = 'Suggest feeds you know for this interest.';

    public function __construct(
        private readonly ScoutResponseParser $parser,
        private readonly ProposalIntake $intake,
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

            if ($this->intake->take($proposal, $interest, SourceProposalOrigin::Scout)) {
                $stored++;
            }
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
