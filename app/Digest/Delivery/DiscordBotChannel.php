<?php

declare(strict_types=1);

namespace App\Digest\Delivery;

use App\Digest\Discord\DiscordClient;
use App\Digest\Discord\DiscordException;
use App\Digest\Discord\ForumTagMatcher;
use App\Digest\Rendering\RenderedPost;
use App\Models\Delivery;
use App\Models\Post;

final class DiscordBotChannel implements Channel
{
    private const MAX_TITLE = 100;

    public function __construct(
        private readonly DiscordClient $discord,
        private readonly ForumTagMatcher $tags,
    ) {}

    public function deliver(Post $post, Delivery $delivery): void
    {
        $rendered = RenderedPost::fromArray($post->rendered ?? []);

        try {
            if ($delivery->external_id === null) {
                $channelId = $delivery->channel->settings['channel_id'] ?? '';
                $threadId = $this->discord->createForumPost(
                    $channelId,
                    mb_substr($rendered->title, 0, self::MAX_TITLE),
                    $rendered->messages[0],
                    $this->tags->tagIds($channelId, $rendered->tagSlugs),
                );
                $delivery->update(['external_id' => $threadId, 'progress' => 1]);
            }

            for ($i = $delivery->progress; $i < count($rendered->messages); $i++) {
                $this->discord->postMessage((string) $delivery->external_id, $rendered->messages[$i]);
                $delivery->update(['progress' => $i + 1]);
            }
        } catch (DiscordException $e) {
            if ($e->status === 403) {
                $delivery->channel->update(['enabled' => false]);
            }

            throw $e;
        }
    }
}
