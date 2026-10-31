<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Source;
use App\Models\User;

final class SourcePolicy
{
    public function view(?User $user, Source $source): bool
    {
        return $source->user_id === null || $user?->id === $source->user_id;
    }

    public function update(User $user, Source $source): bool
    {
        return $user->id === $source->user_id;
    }

    public function delete(User $user, Source $source): bool
    {
        return $user->id === $source->user_id;
    }
}
