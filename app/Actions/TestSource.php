<?php

declare(strict_types=1);

namespace App\Actions;

use App\Digest\Sources\Candidate;
use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\FetchException;
use App\Digest\Sources\ImapInboxFetcher;
use App\Digest\Sources\ResendInboxFetcher;
use App\Enums\SourceType;
use App\Models\Source;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class TestSource
{
    public function __construct(private readonly FeedFetcher $feed, private readonly ResendInboxFetcher $inbox, private readonly ImapInboxFetcher $imap) {}

    /**
     * @return array{ok: bool, message: string, headlines: list<string>}
     */
    public function handle(User $user, SourceType $type, string $url, string $apiKey, string $address = '', string $imapHost = '', string $imapUsername = '', string $imapPassword = '', string $imapFolder = 'INBOX'): array
    {
        try {
            $source = new Source(['type' => $type, 'url' => $url, 'credentials' => $type === SourceType::ImapInbox
                ? ['host' => $imapHost, 'username' => $imapUsername, 'password' => $imapPassword, 'folder' => $imapFolder ?: 'INBOX']
                : ['api_key' => $apiKey, 'address' => $type === SourceType::ResendInbox ? $address : null], 'max_items' => PHP_INT_MAX]);
            $source->user()->associate($user);
            $fetcher = match ($type) {
                SourceType::Feed => $this->feed,
                SourceType::ImapInbox => $this->imap,
                default => $this->inbox,
            };
            $posts = $fetcher->fetch($source, CarbonImmutable::now()->subDays(30));

            return [
                'ok' => true,
                'message' => $posts === [] ? __('No recent posts found.') : __('Found :count recent posts.', ['count' => count($posts)]),
                'headlines' => array_map(fn (Candidate $post) => $post->title, array_slice($posts, 0, 5)),
            ];
        } catch (Throwable $exception) {
            $secrets = array_filter([$apiKey, rawurlencode($apiKey), $address, rawurlencode($address), $imapUsername, rawurlencode($imapUsername), $imapPassword, rawurlencode($imapPassword)], fn (string $value) => $value !== '');
            Log::warning('Source test failed.', [
                'type' => $type->value,
                'error' => Str::limit(str_replace($secrets, '[redacted]', $exception->getMessage()), 500),
            ]);

            return ['ok' => false, 'message' => $type === SourceType::ImapInbox && $exception instanceof FetchException
                ? __($exception->getMessage())
                : __('Could not read this source. Check its address or key and try again.'), 'headlines' => []];
        }
    }
}
