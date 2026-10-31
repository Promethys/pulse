<?php

declare(strict_types=1);

namespace App\Reading;

use App\Enums\InterestCategory;
use App\Http\Resources\InterestResource;
use App\Http\Resources\SourceResource;
use App\Models\Interest;
use App\Models\Source;
use App\Models\SourceProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class SourcesQuery
{
    /**
     * @return list<array{interest: array<string, mixed>, sources: list<array<string, mixed>>}>
     */
    public function catalog(User $user): array
    {
        $order = array_flip(array_map(fn (InterestCategory $category) => $category->value, InterestCategory::cases()));

        return array_values($user->interests()->get()
            ->sortBy(fn (Interest $interest) => [$order[$interest->category->value], $interest->position])
            ->map(fn (Interest $interest) => [
                'interest' => (new InterestResource($interest))->resolve(),
                'sources' => array_values($interest->sources()->catalog()
                    ->whereHas('subscriptions', fn (Builder $query) => $query->where('user_id', $user->id))
                    ->with(['interests', 'subscriptions' => fn ($query) => $query->where('user_id', $user->id)])
                    ->orderBy('name')->get()->map($this->serialize(...))->all()),
            ])->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function custom(User $user): array
    {
        $proposed = SourceProposal::query()->whereIn('url', Source::query()->where('user_id', $user->id)->select('url'))->pluck('url');

        return array_values(Source::query()->where('user_id', $user->id)
            ->with(['interests', 'subscriptions' => fn ($query) => $query->where('user_id', $user->id)])
            ->orderBy('name')->get()->map(fn (Source $source): array => [
                ...$this->serialize($source),
                'suggested' => $source->url !== null && $proposed->contains($source->url),
            ])->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Source $source): array
    {
        $subscription = $source->subscriptions->first();
        $resource = (new SourceResource($source))->resolve();

        return [
            ...$resource,
            'subscription_enabled' => $subscription !== null && $subscription->enabled,
        ];
    }
}
