<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/** Picks and summarizes the day's news from the collected candidates. */
class NewsCurator implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  list<string>  $existingTags
     * @param  list<string>  $interests
     */
    public function __construct(
        private readonly string $profile,
        private readonly array $existingTags,
        private readonly int $maxItems,
        private readonly array $interests = [],
    ) {}

    public function instructions(): string
    {
        $lines = ['You curate a daily news digest for one reader.'];

        if (trim($this->profile) !== '') {
            $lines[] = "Reader profile: {$this->profile}";
        }

        if ($this->interests !== []) {
            $lines[] = 'Reader interests: '.implode(', ', $this->interests);
        }

        return implode("\n", [
            ...$lines,
            '',
            "From the candidates, pick at most {$this->maxItems} items that matter to this reader. Picking none is fine on a quiet day.",
            'Prefer substantial news: releases, important announcements, notable engineering write-ups. Skip promotions, job posts and low-signal discussions.',
            'When several candidates cover the same story, keep only the best source.',
            'A newsletter candidate may contain several stories: you may pick several items from it, each with its own link.',
            '',
            'For each item:',
            '- candidate_id: the id of the candidate it comes from.',
            "- url: one of that candidate's links, copied exactly. Never invent or change a link.",
            '- title: plain text, at most 80 characters.',
            '- summary: one plain-text sentence of at most 160 characters saying what is new and why it matters.',
            '- tags: 1 to 3 lowercase slugs. Reuse an existing tag whenever one fits; create a new one only when none fits.',
            '',
            'Existing tags: '.($this->existingTags === [] ? '(none yet)' : implode(', ', $this->existingTags)),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()->items($schema->object(fn (JsonSchema $schema) => [
                'candidate_id' => $schema->integer()->required(),
                'url' => $schema->string()->required(),
                'title' => $schema->string()->required(),
                'summary' => $schema->string()->required(),
                'tags' => $schema->array()->items($schema->string())->required(),
            ]))->required(),
        ];
    }
}
