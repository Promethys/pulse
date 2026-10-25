<?php

declare(strict_types=1);

namespace App\Digest\Discord;

use App\Models\Tag;

final class ForumTagMatcher
{
    private const MAX_FORUM_TAGS = 20;

    private const MAX_POST_TAGS = 5;

    private const MAX_TAG_NAME = 20;

    public function __construct(private readonly DiscordClient $discord) {}

    /**
     * @param  list<string>  $slugs
     * @return list<string>
     */
    public function tagIds(string $channelId, array $slugs): array
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
