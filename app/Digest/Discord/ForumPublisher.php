<?php

declare(strict_types=1);

namespace App\Digest\Discord;

use App\Digest\Rendering\RenderedPost;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Tag;

/** Publishes a rendered post as a forum thread, resuming where a previous attempt stopped. */
final class ForumPublisher
{
    private const MAX_FORUM_TAGS = 20;

    private const MAX_POST_TAGS = 5;

    private const MAX_TAG_NAME = 20;

    private const MAX_TITLE = 100;

    public function __construct(private readonly DiscordClient $discord) {}

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
                $this->forumTagIds($channelId, $rendered->tagSlugs),
            );
            $post->update(['discord_thread_id' => $threadId, 'messages_sent' => 1]);
        }

        for ($i = $post->messages_sent; $i < count($rendered->messages); $i++) {
            $this->discord->postMessage((string) $post->discord_thread_id, $rendered->messages[$i]);
            $post->update(['messages_sent' => $i + 1]);
        }

        $post->update(['status' => PostStatus::Posted, 'posted_at' => now(), 'last_error' => null]);
    }

    /**
     * Discord ids of the post's tags in this forum (each forum has its own ids, so tags are matched by name),
     * creating missing forum tags while the forum has room (20 max).
     *
     * @param  list<string>  $slugs
     * @return list<string>
     */
    private function forumTagIds(string $channelId, array $slugs): array
    {
        $tags = Tag::query()->whereIn('slug', $slugs)->get()->keyBy('slug');
        $forumTags = $this->discord->forumTags($channelId);

        $missing = [];
        foreach ($slugs as $slug) {
            $tag = $tags->get($slug);
            if ($tag !== null && $this->findId($forumTags, $tag) === null) {
                $missing[] = $tag;
            }
        }

        $room = self::MAX_FORUM_TAGS - count($forumTags);
        if ($missing !== [] && $room > 0) {
            $new = array_map(fn (Tag $tag) => ['name' => $this->forumName($tag)], array_slice($missing, 0, $room));
            $forumTags = $this->discord->setForumTags($channelId, [...$forumTags, ...$new]);
        }

        $ids = [];
        foreach ($slugs as $slug) {
            $tag = $tags->get($slug);
            $id = $tag === null ? null : $this->findId($forumTags, $tag);

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return array_slice($ids, 0, self::MAX_POST_TAGS);
    }

    /**
     * @param  list<array<string, mixed>>  $forumTags
     */
    private function findId(array $forumTags, Tag $tag): ?string
    {
        foreach ($forumTags as $forumTag) {
            $id = (string) ($forumTag['id'] ?? '');
            if ($id !== '' && ($forumTag['name'] ?? null) === $this->forumName($tag)) {
                return $id;
            }
        }

        return null;
    }

    private function forumName(Tag $tag): string
    {
        return mb_substr($tag->name, 0, self::MAX_TAG_NAME);
    }
}
