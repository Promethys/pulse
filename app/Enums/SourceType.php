<?php

declare(strict_types=1);

namespace App\Enums;

use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\GithubTrendingFetcher;
use App\Digest\Sources\ResendInboxFetcher;
use App\Digest\Sources\SourceFetcher;

enum SourceType: string
{
    case Feed = 'feed';
    case GithubTrending = 'github_trending';
    case ResendInbox = 'resend_inbox';

    /**
     * @return class-string<SourceFetcher>
     */
    public function fetcher(): string
    {
        return match ($this) {
            self::Feed => FeedFetcher::class,
            self::GithubTrending => GithubTrendingFetcher::class,
            self::ResendInbox => ResendInboxFetcher::class,
        };
    }
}
