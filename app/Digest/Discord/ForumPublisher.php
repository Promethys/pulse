<?php

declare(strict_types=1);

namespace App\Digest\Discord;

use App\Digest\Rendering\RenderedPost;
use App\Enums\PostStatus;
use App\Models\Post;

/** Publishes a rendered post as a forum thread, resuming where a previous attempt stopped. */
final class ForumPublisher
{
    private const MAX_TITLE = 100;

    public function __construct(
        private readonly DiscordClient $discord,
        private readonly ForumTagMatcher $tags,
    ) {}

    /**
     * @throws DiscordException progress so far is saved on the post
     */
    public function publish(Post $post, string $channelId): void
    {
        $rendered = RenderedPost::fromArray($post->rendered ?? []);

        if ($rendered->messages === []) {
            throw new DiscordException("Post {$post->id} has no rendered messages");
        }

        if ($post->discord_thread_id === null) {
            $threadId = $this->discord->createForumPost(
                $channelId,
                mb_substr($rendered->title, 0, self::MAX_TITLE),
                $rendered->messages[0],
                $this->tags->tagIds($channelId, $rendered->tagSlugs),
            );
            $post->update(['discord_thread_id' => $threadId, 'messages_sent' => 1]);
        }

        for ($i = $post->messages_sent; $i < count($rendered->messages); $i++) {
            $this->discord->postMessage((string) $post->discord_thread_id, $rendered->messages[$i]);
            $post->update(['messages_sent' => $i + 1]);
        }

        $post->update(['status' => PostStatus::Posted, 'posted_at' => now(), 'last_error' => null]);
    }
}
