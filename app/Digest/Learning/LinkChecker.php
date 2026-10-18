<?php

declare(strict_types=1);

namespace App\Digest\Learning;

use App\Digest\Sources\FeedFetcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Links suggested by the model are checked before being posted. */
final class LinkChecker
{
    public function alive(string $url): bool
    {
        if (! preg_match('#^https?://#i', $url)) {
            return false;
        }

        $http = Http::timeout(5)->withUserAgent(FeedFetcher::USER_AGENT);

        try {
            $response = $http->head($url);

            if (in_array($response->status(), [403, 405], true)) {
                $response = $http->get($url);
            }
        } catch (ConnectionException) {
            return false;
        }

        return $response->status() < 400;
    }
}
