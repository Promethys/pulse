<?php

declare(strict_types=1);

namespace App\Digest\Delivery;

use App\Models\Delivery;
use App\Models\Post;

final class InAppChannel implements Channel
{
    public function deliver(Post $post, Delivery $delivery): void {}
}
