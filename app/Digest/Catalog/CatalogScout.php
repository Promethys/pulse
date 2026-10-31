<?php

declare(strict_types=1);

namespace App\Digest\Catalog;

use App\Ai\Agents\SourceScout;
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
    public function __construct(
        private readonly ScoutResponseParser $parser,
        private readonly ProposalChecker $checker,
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
        $agent = new SourceScout($interest->name, $max, $domains);

        try {
            $response = $agent->prompt(
                'Find new feeds for this interest.',
                provider: ScoutProviders::chain(),
                timeout: 120,
            );
        } catch (Throwable $e) {
            report($e);

            return 0;
        }

        $stored = 0;

        foreach ($this->parser->parse($response->text) as $proposal) {
            if ($stored >= $max) {
                break;
            }

            if (SourceProposal::query()->where('url', $proposal['url'])->exists()) {
                continue;
            }

            $check = $this->checker->check($proposal['url']);

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
}
