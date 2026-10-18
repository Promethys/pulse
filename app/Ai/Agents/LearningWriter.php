<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/** Writes the daily developer tip and the explanation of the topic of the day. */
class LearningWriter implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  list<string>  $recentTipTitles
     * @param  list<string>  $existingTags
     */
    public function __construct(
        private readonly string $profile,
        private readonly array $recentTipTitles,
        private readonly array $existingTags,
    ) {}

    public function instructions(): string
    {
        $lines = ['You write a daily learning post for one software developer: a practical tip and the explanation of a software-engineering topic.'];

        if (trim($this->profile) !== '') {
            $lines[] = "Reader profile: {$this->profile}";
        }

        return implode("\n", [
            ...$lines,
            'Level: intermediate to advanced, never basics. No beginner material such as how to declare a variable; prefer something non-obvious that is worth researching further.',
            '',
            'Tip:',
            '- One practical, specific tip (git, the command line, Laravel, PHP, Claude Code, debugging, tooling…), not related to the topic.',
            '- title: plain text, at most 80 characters.',
            '- body: Markdown, a few lines, at most 600 characters; a short code snippet is welcome.',
            '',
            'Topic:',
            '- body: Markdown, about 5 lines, at most 900 characters, explaining the idea, why it matters and when to use it.',
            '- laravel_angle: one short paragraph on how it applies in Laravel or PHP, or an empty string when it does not apply.',
            '- links: 1 or 2 references you are certain exist (official documentation or well-known articles), each with a title and a URL.',
            '',
            'Tags for both: 1 to 3 lowercase slugs. Reuse an existing tag whenever one fits.',
            'Existing tags: '.($this->existingTags === [] ? '(none yet)' : implode(', ', $this->existingTags)),
            '',
            'Do not repeat any of these recent tips: '.($this->recentTipTitles === [] ? '(none yet)' : implode(' | ', $this->recentTipTitles)),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tip' => $schema->object(fn (JsonSchema $schema) => [
                'title' => $schema->string()->required(),
                'body' => $schema->string()->required(),
                'tags' => $schema->array()->items($schema->string())->required(),
            ])->required(),
            'topic' => $schema->object(fn (JsonSchema $schema) => [
                'body' => $schema->string()->required(),
                'laravel_angle' => $schema->string()->required(),
                'links' => $schema->array()->items($schema->object(fn (JsonSchema $schema) => [
                    'title' => $schema->string()->required(),
                    'url' => $schema->string()->required(),
                ]))->required(),
                'tags' => $schema->array()->items($schema->string())->required(),
            ])->required(),
        ];
    }
}
