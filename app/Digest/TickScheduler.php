<?php

declare(strict_types=1);

namespace App\Digest;

use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\Post;
use Carbon\CarbonImmutable;

/** Decides from local time and the posts table what a cron tick should do; the cron itself runs in UTC. */
final class TickScheduler
{
    /**
     * @return list<PostKind> news before learning
     */
    public function due(CarbonImmutable $nowUtc): array
    {
        $local = $nowUtc->setTimezone((string) config('digest.timezone'));
        $due = [];

        foreach ([[PostKind::News, 'digest.news_time'], [PostKind::Learning, 'digest.learning_time']] as [$kind, $timeKey]) {
            if ($local->lessThan($local->setTimeFromTimeString((string) config($timeKey)))) {
                continue;
            }

            $post = Post::query()->whereDate('date', $local->toDateString())->where('kind', $kind)->first();

            if ($post === null || ($post->status !== PostStatus::Posted && $post->attempts < (int) config('digest.max_attempts'))) {
                $due[] = $kind;
            }
        }

        return $due;
    }

    public function localToday(CarbonImmutable $nowUtc): CarbonImmutable
    {
        return $nowUtc->setTimezone((string) config('digest.timezone'))->startOfDay();
    }
}
