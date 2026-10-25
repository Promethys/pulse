<?php

declare(strict_types=1);

namespace App\Digest\Learning;

use App\Digest\Sources\FeedFetcher;
use App\Digest\Support\PublicUrl;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Links suggested by the model are checked before being posted, on public hosts only (each redirect included). */
final class LinkChecker
{
    private const MAX_REDIRECTS = 3;

    private readonly PublicUrl $publicUrl;

    public function __construct(?PublicUrl $publicUrl = null)
    {
        $this->publicUrl = $publicUrl ?? app(PublicUrl::class);
    }

    public function alive(string $url): bool
    {
        $http = Http::timeout(5)->withUserAgent(FeedFetcher::USER_AGENT)->withoutRedirecting();

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (! $this->publicUrl->isPublic($url)) {
                return false;
            }

            try {
                $response = $http->head($url);

                if (in_array($response->status(), [403, 405], true)) {
                    $response = $http->get($url);
                }
            } catch (ConnectionException) {
                return false;
            }

            if (! $response->redirect()) {
                return $response->status() < 400;
            }

            $location = $response->header('Location');
            if ($location === '') {
                return false;
            }

            $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));
        }

        return false;
    }
}
