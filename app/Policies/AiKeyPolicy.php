<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AiKey;
use App\Models\User;

final class AiKeyPolicy
{
    public function view(User $user, AiKey $key): bool
    {
        return $user->id === $key->user_id;
    }

    public function update(User $user, AiKey $key): bool
    {
        return $user->id === $key->user_id;
    }

    public function delete(User $user, AiKey $key): bool
    {
        return $user->id === $key->user_id;
    }
}
