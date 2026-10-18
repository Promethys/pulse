<?php

declare(strict_types=1);

namespace App\Digest\Learning;

use App\Models\LearningTopic;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class TopicPicker
{
    /**
     * Today's topic: the one already picked today (retries), else never-used topics first, then the
     * least recently used, avoiding yesterday's category when another one is available.
     */
    public function next(CarbonImmutable $today): LearningTopic
    {
        $alreadyPicked = LearningTopic::query()->whereDate('last_used_on', $today->toDateString())->first();
        if ($alreadyPicked !== null) {
            return $alreadyPicked;
        }

        $previousCategory = LearningTopic::query()->whereNotNull('last_used_on')->latest('last_used_on')->value('category');

        $topic = $this->ordered()->where('category', '!=', $previousCategory ?? '')->first() ?? $this->ordered()->firstOrFail();
        $topic->update(['last_used_on' => $today->toDateString()]);

        return $topic;
    }

    /**
     * @return Builder<LearningTopic>
     */
    private function ordered(): Builder
    {
        return LearningTopic::query()
            ->orderByRaw('last_used_on IS NOT NULL')
            ->orderBy('last_used_on')
            ->orderBy('id');
    }
}
