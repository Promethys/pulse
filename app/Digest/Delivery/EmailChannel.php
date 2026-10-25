<?php

declare(strict_types=1);

namespace App\Digest\Delivery;

use App\Mail\PostMail;
use App\Models\Delivery;
use App\Models\Post;
use Illuminate\Support\Facades\Mail;

final class EmailChannel implements Channel
{
    public function deliver(Post $post, Delivery $delivery): void
    {
        if ($delivery->progress >= 1) {
            return;
        }

        Mail::to($delivery->channel->settings['address'] ?? '')->send(new PostMail($post));
        $delivery->update(['progress' => 1]);
    }
}
