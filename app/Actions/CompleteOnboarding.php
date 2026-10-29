<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DeliveryChannelType;
use App\Enums\PostKind;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompleteOnboarding
{
    public function handle(User $user, bool $emailDelivery, ?string $email): void
    {
        DB::transaction(function () use ($user, $emailDelivery, $email): void {
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (! $user->interests()->exists()) {
                throw ValidationException::withMessages(['interests' => __('Choose at least one interest.')]);
            }

            if ($emailDelivery) {
                $user->deliveryChannels()->updateOrCreate(['type' => DeliveryChannelType::Email], [
                    'name' => 'Email',
                    'kinds' => [PostKind::News->value, PostKind::Learning->value],
                    'enabled' => true,
                    'settings' => ['address' => $email],
                ]);
            }

            $user->digestSettings()->firstOrCreate()->forceFill(['onboarded_at' => now()])->save();
        });
    }
}
