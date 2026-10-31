<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\InterestCategory;
use App\Enums\InterestStatus;
use App\Enums\SourceProposalStatus;
use App\Jobs\ScoutInterest;
use App\Models\Interest;
use App\Models\InterestProposal;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ApproveInterestProposal
{
    public function handle(InterestProposal $proposal, string $name, InterestCategory $category): Interest
    {
        return DB::transaction(function () use ($proposal, $name, $category): Interest {
            $proposal = $proposal->newQuery()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            if ($proposal->status !== SourceProposalStatus::Pending) {
                throw new DomainException('Only pending interest proposals can be approved.');
            }

            $slug = Str::slug($name);
            if (Interest::query()->where('slug', $slug)->exists()) {
                throw new DomainException('An interest with this name already exists.');
            }

            $interest = Interest::query()->create([
                'slug' => $slug,
                'name' => $name,
                'category' => $category,
                'status' => InterestStatus::Draft,
                'has_learning' => false,
                'position' => ((int) Interest::query()->where('category', $category)->max('position')) + 1,
                'icon' => 'sparkles',
            ]);
            $proposal->update(['status' => SourceProposalStatus::Approved, 'interest_id' => $interest->id, 'name' => $name, 'category' => $category]);
            ScoutInterest::dispatch($interest)->afterCommit();

            return $interest;
        });
    }
}
