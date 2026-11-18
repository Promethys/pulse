<?php

declare(strict_types=1);

namespace App\Digest\Manual;

use App\Enums\PostKind;
use App\Models\User;
use Carbon\CarbonImmutable;

final class RunEligibility
{
    public function check(User $user, PostKind $kind): ?string
    {
        if (! $this->isOnboarded($user)) {
            return 'not_onboarded';
        }
        if (! $user->hasVerifiedEmail()) {
            return 'not_verified';
        }
        if (! $this->hasAi($user)) {
            return 'no_ai';
        }
        if ($this->isPaused($user)) {
            return 'paused';
        }
        if ($kind !== PostKind::News) {
            return 'unsupported_kind';
        }
        if (! $this->isEnabled($user, $kind)) {
            return 'kind_disabled';
        }
        if ($this->hasPost($user, $kind)) {
            return 'already_has_post';
        }

        return null;
    }

    private function isOnboarded(User $user): bool
    {
        return $user->digestSettings?->onboarded_at !== null;
    }

    private function hasAi(User $user): bool
    {
        return $user->uses_instance_ai || $user->aiKeys()->exists();
    }

    private function isPaused(User $user): bool
    {
        $settings = $user->digestSettings;

        return $settings !== null && $settings->isPausedOn(CarbonImmutable::now($settings->timezone));
    }

    private function isEnabled(User $user, PostKind $kind): bool
    {
        $settings = $user->digestSettings;

        return $settings !== null && ($kind === PostKind::News ? $settings->news_enabled : $settings->learning_enabled);
    }

    private function hasPost(User $user, PostKind $kind): bool
    {
        return $user->posts()->where('kind', $kind)->exists();
    }
}
