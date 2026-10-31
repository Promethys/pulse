<?php

declare(strict_types=1);

namespace App\Digest\Catalog;

use App\Enums\SourceProposalKind;
use App\Enums\SourceProposalOrigin;
use App\Enums\SourceProposalStatus;
use App\Models\Interest;
use App\Models\SourceProposal;
use Illuminate\Support\Facades\Log;

final class ProposalIntake
{
    public function __construct(
        private readonly ProposalChecker $checker,
        private readonly MovedFeedFinder $feeds,
    ) {}

    /**
     * @param  array{name: string, url: string, site_url: ?string, reason: string}  $proposal
     */
    public function take(array $proposal, Interest $interest, SourceProposalOrigin $origin): bool
    {
        if (SourceProposal::query()->where('url', $proposal['url'])->exists()) {
            return false;
        }

        $check = $this->checker->check($proposal['url']);

        if ($check->failure === 'feed_parses') {
            $site = $proposal['site_url'] ?: parse_url($proposal['url'], PHP_URL_SCHEME).'://'.parse_url($proposal['url'], PHP_URL_HOST).'/';
            $feed = $this->feeds->advertisedFeed($site);

            if ($feed === null || SourceProposal::query()->where('url', $feed)->exists()) {
                Log::info('Catalog proposal failed a check', ['url' => $proposal['url'], 'failure' => $check->failure]);

                return false;
            }

            $proposal['url'] = $feed;
            $check = $this->checker->check($feed);
        }

        if (! $check->passed) {
            Log::info('Catalog proposal failed a check', ['url' => $proposal['url'], 'failure' => $check->failure]);

            return false;
        }

        SourceProposal::query()->create([
            ...$proposal,
            'origin' => $origin,
            'kind' => SourceProposalKind::NewSource,
            'status' => SourceProposalStatus::Pending,
            'interest_id' => $interest->id,
            'checks' => $check->checks,
        ]);

        return true;
    }
}
