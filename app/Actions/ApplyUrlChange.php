<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SourceProposalKind;
use App\Enums\SourceProposalStatus;
use App\Models\Interest;
use App\Models\Source;
use App\Models\SourceProposal;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ApplyUrlChange
{
    public function handle(SourceProposal $proposal): Source
    {
        return DB::transaction(function () use ($proposal): Source {
            $proposal = $proposal->newQuery()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            if ($proposal->status !== SourceProposalStatus::Pending || $proposal->kind !== SourceProposalKind::UrlChange) {
                throw new DomainException('Only pending URL change proposals can be applied.');
            }

            $source = $proposal->source()->lockForUpdate()->firstOrFail();
            $source->url = $proposal->url;
            $source->last_error = null;
            if ($source->retired_at !== null) {
                $source->enabled = true;
                $source->retired_at = null;
            }
            $source->save();

            $proposal->update(['status' => SourceProposalStatus::Approved]);
            Interest::refreshStatuses();

            return $source;
        });
    }
}
