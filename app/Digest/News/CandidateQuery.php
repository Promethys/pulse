<?php

declare(strict_types=1);

namespace App\Digest\News;

use App\Models\Item;
use App\Models\PostItem;
use App\Models\Source;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class CandidateQuery
{
    /**
     * @return Collection<int, Item>
     */
    public function for(User $user, CarbonImmutable $since): Collection
    {
        $sourceIds = Source::query()->enabled()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->id))
            ->whereHas('subscriptions', fn ($query) => $query->where('user_id', $user->id)->where('enabled', true))
            ->pluck('id');

        return Item::query()
            ->whereHas('sources', fn ($query) => $query->whereIn('sources.id', $sourceIds)->where('item_source.first_seen_at', '>=', $since))
            ->whereNotIn('id', PostItem::query()->select('item_id')->whereHas('post', fn ($query) => $query->where('user_id', $user->id)))
            ->with(['sources' => fn ($query) => $query->whereIn('sources.id', $sourceIds)->orderBy('position')])
            ->get();
    }
}
