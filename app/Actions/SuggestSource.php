<?php

declare(strict_types=1);

namespace App\Actions;

use App\Digest\Catalog\ProposalChecker;
use App\Enums\SourceProposalKind;
use App\Enums\SourceProposalOrigin;
use App\Enums\SourceProposalStatus;
use App\Models\Interest;
use App\Models\InterestProposal;
use App\Models\Source;
use App\Models\SourceProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SuggestSource
{
    public function __construct(private readonly ProposalChecker $checker) {}

    public function handle(User $user, Source $source, Interest $interest, string $reason): SourceProposal
    {
        return DB::transaction(function () use ($user, $source, $interest, $reason): SourceProposal {
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $open = SourceProposal::query()->where('user_id', $user->id)->where('status', SourceProposalStatus::Pending)->count()
                + InterestProposal::query()->where('user_id', $user->id)->where('status', SourceProposalStatus::Pending)->count();
            if ($open >= 10) {
                throw ValidationException::withMessages(['reason' => __('You already have 10 open proposals.')]);
            }
            if (SourceProposal::query()->where('url', $source->url)->exists()) {
                throw ValidationException::withMessages(['reason' => __('This feed has already been suggested.')]);
            }

            $check = $this->checker->check((string) $source->url);
            if (! $check->passed) {
                $message = match ($check->failure) {
                    'public_url' => __('This feed needs a publicly accessible URL.'),
                    'not_in_catalog' => __('This feed is already in the catalog.'),
                    'feed_parses' => __('We could not read this feed. Please check its URL and try again.'),
                    default => __('This feed needs at least three recent posts in the last 30 days.'),
                };

                throw ValidationException::withMessages(['reason' => $message]);
            }

            return SourceProposal::query()->create([
                'origin' => SourceProposalOrigin::User,
                'kind' => SourceProposalKind::NewSource,
                'user_id' => $user->id,
                'interest_id' => $interest->id,
                'url' => $source->url,
                'site_url' => $source->site_url,
                'name' => $source->name,
                'reason' => $reason,
                'checks' => $check->checks,
            ]);
        });
    }
}
