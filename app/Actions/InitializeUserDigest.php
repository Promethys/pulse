<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DeliveryChannelType;
use App\Enums\PostKind;
use App\Models\User;

final class InitializeUserDigest
{
    public function handle(User $user): void
    {
        $user->digestSettings()->create();

        $user->deliveryChannels()->create([
            'name' => 'In the app',
            'type' => DeliveryChannelType::InApp,
            'kinds' => [PostKind::News->value, PostKind::Learning->value],
            'enabled' => true,
            'settings' => null,
        ]);
    }
}
