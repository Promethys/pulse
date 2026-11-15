<?php

declare(strict_types=1);

namespace Tests\Unit\Digest;

use App\Digest\Sources\EmailCandidate;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class EmailCandidateTest extends TestCase
{
    public function test_extracts_plain_text_and_unique_safe_links_with_anchors_first(): void
    {
        $date = CarbonImmutable::parse('2026-11-15 12:00:00 UTC');
        $candidate = EmailCandidate::from('<b>News &amp; tips</b>',
            '<a href="https://example.test/anchor?a=1&amp;b=2">Read</a> <a href="javascript:alert(1)">Bad</a> <a href="https://example.test/unsubscribe">Bye</a> <a href="https://example.test/open">Track</a> <a href="https://list-manage.com/track/1">Track</a>',
            'Text https://example.test/text. https://example.test/text.', $date, 'imap://message/hash');

        $this->assertSame('News & tips', $candidate->title);
        $this->assertSame('Text https://example.test/text. https://example.test/text.', $candidate->excerpt);
        $this->assertSame(['https://example.test/anchor?a=1&b=2', 'https://example.test/text'], $candidate->links);
        $this->assertSame($date, $candidate->publishedAt);
        $this->assertSame('imap://message/hash', $candidate->url);
    }

    public function test_falls_back_to_html_and_no_subject_and_bounds_the_excerpt(): void
    {
        $candidate = EmailCandidate::from('', '<p>'.str_repeat('a', 4000).'</p>', '', CarbonImmutable::now(), 'resend://email/1');

        $this->assertSame('(no subject)', $candidate->title);
        $this->assertSame(str_repeat('a', 2999).'…', $candidate->excerpt);
        $this->assertSame([], $candidate->links);
    }
}
