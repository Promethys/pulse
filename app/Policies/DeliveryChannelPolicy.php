<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DeliveryChannel;
use App\Models\User;

final class DeliveryChannelPolicy
{
    public function view(User $user, DeliveryChannel $channel): bool
    {
        return $user->id === $channel->user_id;
    }

    public function update(User $user, DeliveryChannel $channel): bool
    {
        return $user->id === $channel->user_id;
    }

    public function delete(User $user, DeliveryChannel $channel): bool
    {
        return $user->id === $channel->user_id;
    }
}
