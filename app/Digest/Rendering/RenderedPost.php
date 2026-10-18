<?php

declare(strict_types=1);

namespace App\Digest\Rendering;

final readonly class RenderedPost
{
    /**
     * @param  list<string>  $messages  each at most 2,000 characters; the first one starts the forum thread
     * @param  list<string>  $tagSlugs  at most 5, applied as forum tags
     */
    public function __construct(
        public string $title,
        public array $messages,
        public array $tagSlugs,
    ) {}

    /**
     * @return array{title: string, messages: list<string>, tag_slugs: list<string>}
     */
    public function toArray(): array
    {
        return ['title' => $this->title, 'messages' => $this->messages, 'tag_slugs' => $this->tagSlugs];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $strings = fn (mixed $value) => array_values(array_map(strval(...), is_array($value) ? $value : []));

        return new self((string) ($data['title'] ?? ''), $strings($data['messages'] ?? []), $strings($data['tag_slugs'] ?? []));
    }
}
