<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Digest\Catalog\ProposalIntake;
use App\Enums\SourceProposalOrigin;
use App\Models\Interest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportProposal implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * @param  array{interest: string, name: string, url: string, site_url: ?string, reason: string}  $row
     */
    public function __construct(public array $row) {}

    public function handle(ProposalIntake $intake): void
    {
        $interest = Interest::query()->where('slug', $this->row['interest'])->first();

        if ($interest === null) {
            return;
        }

        $intake->take([
            'name' => $this->row['name'],
            'url' => $this->row['url'],
            'site_url' => $this->row['site_url'],
            'reason' => $this->row['reason'],
        ], $interest, SourceProposalOrigin::Import);
    }
}
