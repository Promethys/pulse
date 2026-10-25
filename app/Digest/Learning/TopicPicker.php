<?php

declare(strict_types=1);

namespace App\Digest\Learning;

use App\Models\LearningTopic;
use App\Models\LearningTopicUsage;
use App\Models\User;
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

    public function nextFor(User $user, CarbonImmutable $today): LearningTopic
    {
        $alreadyPicked = $user->learningTopicUsages()->whereDate('used_on', $today->toDateString())->first();
        if ($alreadyPicked !== null) {
            return $alreadyPicked->topic;
        }

        $previousCategory = $user->learningTopicUsages()->latest('used_on')->latest('id')->first()?->topic->category;

        $topic = $this->orderedFor($user)->where('category', '!=', $previousCategory ?? '')->first()
            ?? $this->orderedFor($user)->firstOrFail();

        $user->learningTopicUsages()->create([
            'learning_topic_id' => $topic->id,
            'used_on' => $today->toDateString(),
        ]);

        return $topic;
    }

    /**
     * @return Builder<LearningTopic>
     */
    private function orderedFor(User $user): Builder
    {
        $usages = LearningTopicUsage::query()
            ->select('learning_topic_id')
            ->selectRaw('MAX(used_on) AS latest_used_on')
            ->where('user_id', $user->id)
            ->groupBy('learning_topic_id');

        return LearningTopic::query()
            ->select('learning_topics.*')
            ->leftJoinSub($usages, 'user_usages', 'user_usages.learning_topic_id', '=', 'learning_topics.id')
            ->orderByRaw('user_usages.latest_used_on IS NOT NULL')
            ->orderBy('user_usages.latest_used_on')
            ->orderBy('learning_topics.id');
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
