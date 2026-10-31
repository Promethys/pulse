<?php

declare(strict_types=1);

namespace App\Digest\News;

use App\Digest\Sources\UrlNormalizer;
use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Keeps only what the model may legitimately return: known candidates, their own links, bounded text. Drops, never repairs. */
final class SelectionValidator
{
    private const TITLE_LENGTH = 80;

    private const SUMMARY_LENGTH = 160;

    private const MAX_TAGS = 3;

    private const FALLBACK_TAG = 'general';

    /**
     * @param  array<mixed>  $data  the model's answer
     * @param  Collection<int, Item>  $candidatesById
     * @param  array<int, string>  $defaultTags  candidate id => default tag
     * @return list<SelectedItem>
     */
    public function validate(array $data, Collection $candidatesById, array $defaultTags = [], ?int $maxItems = null): array
    {
        $selected = [];
        $seenUrls = [];

        foreach (is_array($data['items'] ?? null) ? $data['items'] : [] as $entry) {
            if (count($selected) >= ($maxItems ?? (int) config('digest.max_items'))) {
                break;
            }

            $candidateId = is_array($entry) ? $this->candidateId($entry['candidate_id'] ?? null) : null;
            $candidate = $candidateId !== null ? $candidatesById->get($candidateId) : null;
            $url = is_string($entry['url'] ?? null) ? trim($entry['url']) : '';

            if ($candidate === null || ! $this->isOwnLink($candidate, $url)) {
                continue;
            }

            $normalized = UrlNormalizer::normalize($url);
            if (isset($seenUrls[$normalized])) {
                continue;
            }
            $seenUrls[$normalized] = true;

            $selected[] = new SelectedItem(
                candidateId: $candidate->id,
                url: $url,
                title: Str::limit(trim((string) ($entry['title'] ?? '')), self::TITLE_LENGTH - 1, '…'),
                summary: Str::limit(trim((string) ($entry['summary'] ?? '')), self::SUMMARY_LENGTH - 1, '…'),
                tagSlugs: $this->tags($entry['tags'] ?? null) ?: [$defaultTags[$candidate->id] ?? self::FALLBACK_TAG],
            );
        }

        return $selected;
    }

    /** Some providers send ids as strings ("12"). */
    private function candidateId(mixed $id): ?int
    {
        return match (true) {
            is_int($id) => $id,
            is_string($id) && ctype_digit($id) => (int) $id,
            default => null,
        };
    }

    private function isOwnLink(Item $candidate, string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $normalized = UrlNormalizer::normalize($url);

        foreach ($candidate->links as $link) {
            if (UrlNormalizer::normalize($link) === $normalized) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function tags(mixed $tags): array
    {
        $slugs = array_map(fn (mixed $tag) => is_string($tag) ? Str::slug($tag) : '', is_array($tags) ? $tags : []);

        return array_slice(array_values(array_unique(array_filter($slugs))), 0, self::MAX_TAGS);
    }
}
