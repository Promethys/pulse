<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Catalog\ScoutResponseParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScoutResponseParserTest extends TestCase
{
    #[DataProvider('wrappers')]
    public function test_extracts_json_from_bare_fenced_and_prose_responses(string $before, string $after): void
    {
        $proposal = ['name' => 'Observatory', 'url' => 'https://observatory.test/rss', 'site_url' => 'https://observatory.test', 'reason' => 'Original reporting'];
        $text = $before.json_encode(['proposals' => [$proposal]], JSON_THROW_ON_ERROR).$after;

        $this->assertSame([$proposal], app(ScoutResponseParser::class)->parse($text));
    }

    public function test_skips_invalid_entries_without_losing_valid_siblings_and_normalizes_optional_fields(): void
    {
        $text = json_encode(['proposals' => [
            null, 'invalid', 7, [], ['url' => 'https://missing-name.test/feed'], ['name' => 'Missing URL'],
            ['name' => [], 'url' => 'https://invalid-name.test/feed'], ['name' => 'Invalid URL', 'url' => 42],
            ['name' => '  Observatory  ', 'url' => '  https://observatory.test/rss  ', 'reason' => '  Reporting  ', 'site_url' => '  https://observatory.test  ', 'extra' => 'ignored'],
            ['name' => 'Garden', 'url' => 'https://garden.test/feed'],
            ['name' => 'Space', 'url' => 'https://space.test/feed', 'site_url' => [], 'reason' => ['invalid']],
        ]], JSON_THROW_ON_ERROR);

        $this->assertSame([
            ['name' => 'Observatory', 'url' => 'https://observatory.test/rss', 'site_url' => 'https://observatory.test', 'reason' => 'Reporting'],
            ['name' => 'Garden', 'url' => 'https://garden.test/feed', 'site_url' => null, 'reason' => ''],
            ['name' => 'Space', 'url' => 'https://space.test/feed', 'site_url' => null, 'reason' => ''],
        ], app(ScoutResponseParser::class)->parse($text));
    }

    public function test_trims_and_bounds_unicode_names_and_reasons(): void
    {
        $text = json_encode(['proposals' => [[
            'name' => '  '.str_repeat('é', 121).'  ', 'url' => 'https://space.test/feed',
            'reason' => '  '.str_repeat('星', 501).'  ',
        ]]], JSON_THROW_ON_ERROR);

        $proposal = app(ScoutResponseParser::class)->parse($text)[0];

        $this->assertSame(str_repeat('é', 120), $proposal['name']);
        $this->assertSame(str_repeat('星', 500), $proposal['reason']);
    }

    #[DataProvider('malformedResponses')]
    public function test_malformed_or_unusable_responses_return_no_proposals(string $text): void
    {
        $this->assertSame([], app(ScoutResponseParser::class)->parse($text));
    }

    /** @return array<string, array{string, string}> */
    public static function wrappers(): array
    {
        return [
            'bare JSON' => ['', ''],
            'JSON fence' => ["```json\n", "\n```"],
            'plain fence' => ["```\n", "\n```"],
            'surrounding prose' => ["Here are some feeds:\n", "\nI hope these help."],
            'prose and fence' => ["Results:\n```json\n", "\n```\nDone."],
        ];
    }

    /** @return array<string, array{string}> */
    public static function malformedResponses(): array
    {
        return [
            'empty' => [''], 'prose only' => ['No feeds found.'], 'broken JSON' => ['{"proposals":['],
            'null' => ['null'], 'scalar' => ['42'], 'missing proposals' => ['{}'],
            'null proposals' => ['{"proposals":null}'], 'string proposals' => ['{"proposals":"invalid"}'],
            'empty proposals' => ['{"proposals":[]}'],
        ];
    }
}
