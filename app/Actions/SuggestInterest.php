<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\InterestCategory;
use App\Enums\SourceProposalStatus;
use App\Models\Interest;
use App\Models\InterestProposal;
use App\Models\SourceProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SuggestInterest
{
    public function handle(User $user, string $name, InterestCategory $category, string $reason): InterestProposal
    {
        return DB::transaction(function () use ($user, $name, $category, $reason): InterestProposal {
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $open = SourceProposal::query()->where('user_id', $user->id)->where('status', SourceProposalStatus::Pending)->count()
                + InterestProposal::query()->where('user_id', $user->id)->where('status', SourceProposalStatus::Pending)->count();
            if ($open >= 10) {
                throw ValidationException::withMessages(['reason' => __('You already have 10 open proposals.')]);
            }
            if (Interest::query()->where('slug', Str::slug($name))->exists()
                || InterestProposal::query()->where('status', SourceProposalStatus::Pending)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
                throw ValidationException::withMessages(['name' => __('This interest already exists or has already been suggested.')]);
            }

            return InterestProposal::query()->create([
                'user_id' => $user->id,
                'name' => $name,
                'category' => $category,
                'reason' => $reason,
            ]);
        });
    }
}
