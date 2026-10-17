<?php

declare(strict_types=1);

namespace App\Enums;

enum SourceType: string
{
    case Feed = 'feed';
    case GithubTrending = 'github_trending';
    case ResendInbox = 'resend_inbox';
}
