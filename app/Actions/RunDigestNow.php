<?php

declare(strict_types=1);

namespace App\Actions;

use App\Digest\Manual\RunEligibility;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Jobs\RunDigest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class RunDigestNow
{
    public function __construct(private readonly RunEligibility $eligibility) {}

    public function handle(User $user, PostKind $kind): void
    {
        $reason = $this->eligibility->check($user, $kind);
        if ($reason !== null) {
            throw ValidationException::withMessages(['kind' => match ($reason) {
                'not_onboarded' => __('Complete setup before generating a digest.'),
                'not_verified' => __('Verify your email before generating a digest.'),
                'no_ai' => __('Add an AI key before generating a digest.'),
                'paused' => __('Resume your digests before generating one.'),
                'kind_disabled' => __('Enable this digest before generating it.'),
                'unsupported_kind' => __('This kind of digest cannot be generated yet.'),
                'already_has_post' => __('Your digest has already been started.'),
                default => __('This digest cannot be generated right now.'),
            }]);
        }

        $settings = $user->digestSettings;
        assert($settings !== null);
        $date = CarbonImmutable::now($settings->timezone)->startOfDay();
        $user->posts()->firstOrCreate(
            ['date' => $date->toDateString(), 'kind' => $kind],
            ['status' => PostStatus::Pending],
        );
        RunDigest::dispatch($user, $kind, $date);
    }
}
