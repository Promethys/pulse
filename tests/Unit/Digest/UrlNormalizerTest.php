<?php

declare(strict_types=1);

namespace Tests\Unit\Digest;

use App\Digest\Sources\UrlNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UrlNormalizerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function urls(): array
    {
        return [
            'host case, www, tracking, order, fragment, slash' => ['https://WWW.Example.com/a/?utm_source=x&b=2&a=1#top', 'https://example.com/a?a=1&b=2'],
            'root slash' => ['http://example.com/', 'http://example.com'],
            'all tracking params removed' => ['https://example.com/a?ref=hn&fbclid=1&gclid=2&mc_cid=3&mc_eid=4&utm_medium=rss', 'https://example.com/a'],
            'path case kept' => ['https://example.com/Some/Path', 'https://example.com/Some/Path'],
            'port kept' => ['https://example.com:8443/a', 'https://example.com:8443/a'],
            'dots in param names kept' => ['https://example.com/a?v.1=x', 'https://example.com/a?v.1=x'],
            'non-http scheme' => ['resend://email/AbC123', 'resend://email/AbC123'],
            'surrounding spaces' => ['  https://example.com/a  ', 'https://example.com/a'],
        ];
    }

    #[DataProvider('urls')]
    public function test_normalizes(string $input, string $expected): void
    {
        $this->assertSame($expected, UrlNormalizer::normalize($input));
    }

    public function test_equivalent_urls_normalize_identically(): void
    {
        $this->assertSame(
            UrlNormalizer::normalize('https://example.com/a'),
            UrlNormalizer::normalize('https://www.example.com/a/?utm_source=x'),
        );
    }
}
