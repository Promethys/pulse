<?php

declare(strict_types=1);

namespace Tests\Unit\Digest;

use App\Digest\Rendering\MessageSplitter;
use PHPUnit\Framework\TestCase;

class MessageSplitterTest extends TestCase
{
    public function test_packs_blocks_under_the_limit(): void
    {
        $messages = (new MessageSplitter)->split(['aaaa', 'bbbb', 'cccc'], limit: 10);

        $this->assertSame(["aaaa\n\nbbbb", 'cccc'], $messages);
    }

    public function test_never_exceeds_the_limit(): void
    {
        mt_srand(42);
        $blocks = [];
        for ($i = 0; $i < 60; $i++) {
            $lines = [];
            for ($j = 0, $n = mt_rand(1, 12); $j < $n; $j++) {
                $lines[] = str_repeat('x', mt_rand(1, 400));
            }
            $blocks[] = implode("\n", $lines);
        }

        $messages = (new MessageSplitter)->split($blocks);

        foreach ($messages as $message) {
            $this->assertLessThanOrEqual(2000, mb_strlen($message));
            $this->assertNotSame('', trim($message));
        }
        $this->assertSame(
            str_replace(["\n"], '', implode('', $blocks)),
            str_replace(["\n"], '', implode('', $messages)),
        );
    }

    public function test_splits_an_oversized_block_on_lines(): void
    {
        $block = implode("\n", ['## AI', str_repeat('a', 8), str_repeat('b', 8)]);

        $this->assertSame(["## AI\naaaaaaaa", 'bbbbbbbb'], (new MessageSplitter)->split([$block], limit: 15));
    }

    public function test_repeats_the_heading_on_continuations(): void
    {
        $block = implode("\n", ['## AI', str_repeat('a', 20), str_repeat('b', 20), str_repeat('c', 20)]);

        $this->assertSame(
            ['## AI'."\n".str_repeat('a', 20), "## AI (continued)\n".str_repeat('b', 20), "## AI (continued)\n".str_repeat('c', 20)],
            (new MessageSplitter)->split([$block], limit: 40),
        );
    }

    public function test_drops_the_continuation_heading_when_it_would_not_fit(): void
    {
        $block = implode("\n", ['## AI', str_repeat('a', 30), str_repeat('b', 30)]);

        $messages = (new MessageSplitter)->split([$block], limit: 40);

        $this->assertSame(str_repeat('b', 30), $messages[1]);
    }

    public function test_hard_cuts_a_single_line_longer_than_the_limit(): void
    {
        $this->assertSame(['aaaaa', 'aaaaa', 'aa'], (new MessageSplitter)->split([str_repeat('a', 12)], limit: 5));
    }

    public function test_ignores_empty_blocks(): void
    {
        $this->assertSame(['a'], (new MessageSplitter)->split(['', 'a', '  ']));
    }
}
