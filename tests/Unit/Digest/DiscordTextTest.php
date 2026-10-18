<?php

declare(strict_types=1);

namespace Tests\Unit\Digest;

use App\Digest\Rendering\DiscordText;
use PHPUnit\Framework\TestCase;

class DiscordTextTest extends TestCase
{
    public function test_escapes_markdown(): void
    {
        $this->assertSame('\*\*bold\*\* \_x\_ \~\~s\~\~ \`code\` \|\|spoiler\|\| \> quote \[link\]', DiscordText::escape('**bold** _x_ ~~s~~ `code` ||spoiler|| > quote [link]'));
    }

    public function test_escapes_backslashes_first(): void
    {
        $this->assertSame('C:\\\\path\*', DiscordText::escape('C:\\path*'));
    }

    public function test_neutralizes_mentions(): void
    {
        $escaped = DiscordText::escape('@everyone @here <@123> <@!123> <#456> <@&789>');

        $this->assertStringNotContainsString('@everyone', $escaped);
        $this->assertStringNotContainsString('@here', $escaped);
        $this->assertDoesNotMatchRegularExpression('/<[@#]/', $escaped);
    }

    public function test_leaves_plain_text_alone(): void
    {
        $this->assertSame('Laravel 13.18 adds deferred batches.', DiscordText::escape('Laravel 13.18 adds deferred batches.'));
    }
}
