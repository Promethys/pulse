<?php

declare(strict_types=1);

namespace App\Digest;

use App\Enums\PostKind;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class TickScheduler
{
    /**
     * @return list<DueDigest>
     */
    public function due(CarbonImmutable $nowUtc): array
    {
        $users = User::query()
            ->whereNotNull('email_verified_at')
            ->whereHas('digestSettings', fn (Builder $query) => $query->whereNotNull('onboarded_at'))
            ->where(fn (Builder $query) => $query->whereHas('aiKeys')->orWhere('uses_instance_ai', true))
            ->with('digestSettings')
            ->orderBy('id')
            ->get();
        $candidates = [];

        foreach ($users as $user) {
            $settings = $user->digestSettings;

            if ($settings === null) {
                continue;
            }

            $local = $nowUtc->setTimezone($settings->timezone);

            foreach (PostKind::cases() as $kind) {
                $enabled = $kind === PostKind::News ? $settings->news_enabled : $settings->learning_enabled;
                $time = $kind === PostKind::News ? $settings->news_time : $settings->learning_time;

                if (! $enabled || $local->lessThan($local->setTimeFromTimeString($time))) {
                    continue;
                }

                if ($kind === PostKind::Learning && ! $user->learningAvailable()) {
                    continue;
                }

                $candidates[] = new DueDigest($user, $kind, $local->startOfDay());
            }
        }

        if ($candidates === []) {
            return [];
        }

        $dates = array_map(fn (DueDigest $digest): string => $digest->date->toDateString(), $candidates);
        $posts = Post::query()
            ->whereIn('user_id', $users->modelKeys())
            ->whereIn('date', array_unique($dates))
            ->get()
            ->keyBy(fn (Post $post): string => $post->user_id.':'.$post->kind->value.':'.$post->date->toDateString());
        $due = [];

        foreach ($candidates as $digest) {
            $post = $posts->get($digest->user->id.':'.$digest->kind->value.':'.$digest->date->toDateString());

            if ($post === null || ($post->rendered === null && $post->attempts < (int) config('digest.max_attempts'))) {
                $due[] = $digest;
            }
        }

        return $due;
    }
}
