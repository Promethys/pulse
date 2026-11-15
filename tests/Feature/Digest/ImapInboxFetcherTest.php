<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Sources\FetchException;
use App\Digest\Sources\Imap\FolderNotFoundException;
use App\Digest\Sources\Imap\ImapMessage;
use App\Digest\Sources\Imap\Mailbox;
use App\Digest\Sources\ImapInboxFetcher;
use App\Digest\Support\PublicUrl;
use App\Enums\SourceType;
use App\Models\Source;
use Carbon\CarbonImmutable;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;

final class ImapInboxFetcherTest extends TestCase
{
    public function test_filters_exact_dates_orders_and_limits_messages_and_pins_the_checked_address(): void
    {
        $since = CarbonImmutable::parse('2026-11-15 12:00:00 UTC');
        $mailbox = Mockery::mock(Mailbox::class);
        $mailbox->shouldReceive('messagesSince')->once()
            ->with('mail.example.test', '93.184.216.34', 'reader', 'secret', 'Newsletters', $since, 2)
            ->andReturn([
                new ImapMessage(' <BOUNDARY@EXAMPLE.TEST> ', 1, '', '<a href="https://example.test/post">Read</a>', '', $since, '42'),
                new ImapMessage('old', 2, 'Old', '', '', $since->subSecond(), '42'),
                new ImapMessage(null, 3, '<b>Newest</b>', '', 'Read https://example.test/new', $since->addHour(), '42'),
                new ImapMessage('extra', 4, 'Extra', '', '', $since->addMinute(), '42'),
            ]);
        $resolutions = 0;
        $publicUrl = new PublicUrl(function (string $host) use (&$resolutions): array {
            $this->assertSame('mail.example.test', $host);
            $resolutions++;

            return $resolutions === 1 ? ['93.184.216.34'] : ['127.0.0.1'];
        });

        $candidates = (new ImapInboxFetcher($mailbox, $publicUrl))->fetch($this->source(2), $since);

        $this->assertSame(1, $resolutions);
        $this->assertSame(['Newest', 'Extra'], array_column($candidates, 'title'));
        $this->assertSame('imap://message/'.sha1('mail.example.test|Newsletters|42|3'), $candidates[0]->url);
        $this->assertSame('Read https://example.test/new', $candidates[0]->excerpt);
        $this->assertSame(['https://example.test/new'], $candidates[0]->links);
        $this->assertTrue($candidates[0]->publishedAt->equalTo($since->addHour()));
    }

    public function test_includes_the_boundary_and_hashes_normalized_message_ids(): void
    {
        $since = CarbonImmutable::parse('2026-11-15 12:00:00 UTC');
        $mailbox = Mockery::mock(Mailbox::class);
        $mailbox->shouldReceive('messagesSince')->once()->andReturn([
            new ImapMessage(' <BOUNDARY@EXAMPLE.TEST> ', 1, '', '<a href="https://example.test/post">Read</a>', '', $since, '42'),
        ]);

        $candidate = (new ImapInboxFetcher($mailbox, new PublicUrl(fn () => ['93.184.216.34'])))->fetch($this->source(), $since)[0];

        $this->assertSame('(no subject)', $candidate->title);
        $this->assertSame('imap://message/'.sha1('<boundary@example.test>'), $candidate->url);
        $this->assertSame(['https://example.test/post'], $candidate->links);
    }

    public function test_refuses_unsafe_hosts_before_connecting(): void
    {
        $mailbox = Mockery::mock(Mailbox::class);
        $mailbox->shouldNotReceive('messagesSince');
        $fetcher = new ImapInboxFetcher($mailbox, new PublicUrl(fn () => ['93.184.216.34', '127.0.0.1']));

        $this->expectException(FetchException::class);
        $this->expectExceptionMessage('The mail server must be a public mail server name.');

        $fetcher->fetch($this->source(), CarbonImmutable::now());
    }

    #[DataProvider('failures')]
    public function test_maps_errors_without_leaking_credentials(\Throwable $error, string $message): void
    {
        $mailbox = Mockery::mock(Mailbox::class);
        $mailbox->shouldReceive('messagesSince')->once()->andThrow($error);
        $fetcher = new ImapInboxFetcher($mailbox, new PublicUrl(fn () => ['93.184.216.34']));

        try {
            $fetcher->fetch($this->source(), CarbonImmutable::now());
            $this->fail('Expected FetchException');
        } catch (FetchException $exception) {
            $this->assertSame($message, $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }

    public static function failures(): array
    {
        return [
            'authentication' => [new AuthFailedException('reader secret'), 'Could not sign in to the mail server. Check the username and app password.'],
            'folder' => [new FolderNotFoundException, 'Folder Newsletters was not found.'],
            'connection' => [new RuntimeException('reader secret timeout'), 'Could not reach the mail server.'],
            'tls' => [new RuntimeException('reader secret certificate failed'), 'Could not reach the mail server.'],
        ];
    }

    private function source(int $limit = 20): Source
    {
        return new Source(['type' => SourceType::ImapInbox, 'max_items' => $limit, 'credentials' => [
            'host' => 'mail.example.test', 'username' => 'reader', 'password' => 'secret', 'folder' => 'Newsletters',
        ]]);
    }
}
