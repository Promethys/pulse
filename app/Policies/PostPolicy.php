<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

final class PostPolicy
{
    public function view(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }
}
