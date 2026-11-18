<?php

declare(strict_types=1);

namespace App\Reading;

use App\Digest\News\CandidateQuery;
use App\Digest\News\HeadlineOrder;
use App\Models\Item;
use App\Models\User;
use Carbon\CarbonImmutable;

final class HeadlinePreview
{
    public function __construct(private readonly CandidateQuery $candidates, private readonly HeadlineOrder $order) {}

    /**
     * @return list<array{title: string, url: string, source: string|null}>
     */
    public function for(User $user): array
    {
        return array_values($this->order->sort($this->candidates->for($user, CarbonImmutable::now()->subDay()))
            ->take(10)
            ->map(fn (Item $item) => ['title' => $item->title, 'url' => $item->url, 'source' => $item->sources->first()?->name])
            ->all());
    }
}
