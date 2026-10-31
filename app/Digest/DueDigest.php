<?php

declare(strict_types=1);

namespace App\Digest;

use App\Enums\PostKind;
use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class DueDigest
{
    public function __construct(
        public User $user,
        public PostKind $kind,
        public CarbonImmutable $date,
    ) {}
}
