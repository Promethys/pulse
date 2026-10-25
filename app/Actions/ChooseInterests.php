<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Interest;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ChooseInterests
{
    /**
     * @param  Collection<int, Interest>  $interests
     */
    public function handle(User $user, Collection $interests): void
    {
        DB::transaction(function () use ($user, $interests): void {
            $interestIds = $interests->pluck('id')->all();
            $user->interests()->sync($interestIds);

            $sources = Source::query()->catalog()->where('enabled', true)->where('is_default', true)
                ->whereHas('interests', function (Builder $query) use ($interestIds) {
                    $query->whereIn('interests.id', $interestIds);
                })->get();

            foreach ($sources as $source) {
                $user->sourceSubscriptions()->firstOrCreate(['source_id' => $source->id], ['enabled' => true]);
            }

            $user->sourceSubscriptions()->where('enabled', true)
                ->whereHas('source', function (Builder $query) use ($interestIds) {
                    $query->catalog()->whereDoesntHave('interests', function (Builder $query) use ($interestIds) {
                        $query->whereIn('interests.id', $interestIds);
                    });
                })->delete();
        });
    }
}
