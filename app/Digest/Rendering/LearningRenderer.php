<?php

declare(strict_types=1);

namespace App\Digest\Rendering;

use App\Models\LearningPost;
use Carbon\CarbonImmutable;

final class LearningRenderer
{
    private const MAX_FORUM_TAGS = 5;

    public function __construct(private readonly MessageSplitter $splitter) {}

    /** Titles are plain text (escaped); bodies are Markdown written for Discord (kept as is). */
    public function render(CarbonImmutable $date, LearningPost $tip, LearningPost $topic): RenderedPost
    {
        $tipBlock = '## 💡 '.DiscordText::escape($tip->title)."\n".trim($tip->body);

        $topicBlock = '## 📚 '.DiscordText::escape($topic->title)."\n".trim($topic->body);
        if ($topic->links !== []) {
            $topicBlock .= "\n\n**Suggested reading:**\n".implode("\n", array_map(
                fn (array $link) => '- '.DiscordText::escape($link['title'])." <{$link['url']}>",
                $topic->links,
            ));
        }

        $tags = array_values(array_unique([
            ...$tip->tags->pluck('slug')->all(),
            ...$topic->tags->pluck('slug')->all(),
        ]));

        return new RenderedPost(
            '🧠 Tip & topic — '.$date->format('D j M'),
            $this->splitter->split([$tipBlock, $topicBlock]),
            array_slice(array_map(strval(...), $tags), 0, self::MAX_FORUM_TAGS),
        );
    }
}
