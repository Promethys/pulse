<?php

declare(strict_types=1);

namespace App\Digest\Catalog;

use App\Digest\Sources\UrlNormalizer;
use App\Models\Interest;

final class ProposalImportParser
{
    public const int MAX_ROWS = 500;

    public function parse(string $text): ProposalImport
    {
        $start = strpos($text, '[');
        $end = strrpos($text, ']');
        $data = $start === false || $end === false ? null : json_decode(substr($text, $start, $end - $start + 1), true);

        if (! is_array($data) || ! array_is_list($data)) {
            return new ProposalImport([], ['The input is not a JSON array.']);
        }

        if (count($data) > self::MAX_ROWS) {
            return new ProposalImport([], ['Import at most '.self::MAX_ROWS.' rows at a time.']);
        }

        $interests = Interest::query()->pluck('slug')->flip();
        $rows = [];
        $rejected = [];
        $seen = [];

        foreach ($data as $index => $row) {
            $number = $index + 1;
            $error = $this->error($row, $interests->has(is_array($row) && is_string($row['interest'] ?? null) ? $row['interest'] : ''));

            if ($error !== null) {
                $rejected[] = "Row {$number}: {$error}";

                continue;
            }

            /** @var array{interest: string, name: string, url: string, site_url?: ?string, reason?: ?string} $row */
            $url = trim($row['url']);
            $key = UrlNormalizer::normalize(strtolower($url));

            if (isset($seen[$key])) {
                $rejected[] = "Row {$number}: duplicate url in this import";

                continue;
            }

            $seen[$key] = true;
            $rows[] = [
                'interest' => $row['interest'],
                'name' => mb_substr(trim($row['name']), 0, 120),
                'url' => $url,
                'site_url' => is_string($row['site_url'] ?? null) ? trim($row['site_url']) : null,
                'reason' => is_string($row['reason'] ?? null) ? mb_substr(trim($row['reason']), 0, 500) : '',
            ];
        }

        return new ProposalImport($rows, $rejected);
    }

    private function error(mixed $row, bool $knownInterest): ?string
    {
        if (! is_array($row)) {
            return 'not an object';
        }

        if (! $knownInterest) {
            return 'unknown interest';
        }

        if (! is_string($row['name'] ?? null) || trim($row['name']) === '') {
            return 'missing name';
        }

        if (! $this->validUrl($row['url'] ?? null)) {
            return 'invalid url';
        }

        if (($row['site_url'] ?? null) !== null && ! $this->validUrl($row['site_url'])) {
            return 'invalid site_url';
        }

        return null;
    }

    private function validUrl(mixed $url): bool
    {
        return is_string($url)
            && filter_var(trim($url), FILTER_VALIDATE_URL) !== false
            && preg_match('#^https?://#i', trim($url)) === 1;
    }
}
