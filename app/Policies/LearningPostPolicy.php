<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LearningPost;
use App\Models\User;

final class LearningPostPolicy
{
    public function view(User $user, LearningPost $learningPost): bool
    {
        return $learningPost->post()->where('user_id', $user->id)->exists();
    }
}
