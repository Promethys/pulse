<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Sources\Imap\FolderNotFoundException;
use App\Digest\Sources\Imap\WebklexMailbox;
use Carbon\CarbonImmutable;
use Mockery;
use Tests\TestCase;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Config;
use Webklex\PHPIMAP\Connection\Protocols\ImapProtocol;
use Webklex\PHPIMAP\Connection\Protocols\Response;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\IMAP;

final class WebklexMailboxTest extends TestCase
{
    public function test_pins_tls_and_reads_only_with_peek_after_filtering_and_sorting_headers(): void
    {
        $clients = Mockery::mock(ClientManager::class);
        $client = Mockery::mock(Client::class);
        $protocol = Mockery::mock(ImapProtocol::class);
        $folder = Mockery::mock(Folder::class);
        $clients->shouldReceive('make')->once()->with(Mockery::on(fn ($config) => $config['host'] === '93.184.216.34' && $config['port'] === 993 && $config['encryption'] === 'ssl'
            && $config['validate_cert'] === true && $config['timeout'] === 15
            && $config['ssl_options']['peer_name'] === 'mail.example.test'
            && $config['ssl_options']['verify_peer'] === true && $config['ssl_options']['verify_peer_name'] === true
            && $config['username'] === 'reader' && $config['password'] === 'secret'
        ))->andReturn($client);
        $client->shouldReceive('connect')->once()->andReturnSelf();
        $client->shouldReceive('getFolderByPath')->once()->with('Newsletters')->andReturn($folder);
        $folder->shouldReceive('examine')->once()->andReturn(['uidvalidity' => 42]);
        $client->shouldReceive('getConnection')->once()->andReturn($protocol);
        $client->shouldReceive('getConfig')->andReturn(Config::make());
        $client->shouldReceive('disconnect')->once()->andReturnSelf();
        $protocol->shouldReceive('search')->once()->with(['SINCE "15-Nov-2026"'], IMAP::ST_UID)
            ->andReturn($this->response([1, 2, 3]));
        $protocol->shouldReceive('fetch')->once()->with(['UID', 'BODY.PEEK[HEADER]'], [1, 2, 3], null, IMAP::ST_UID)
            ->andReturn($this->response([
                1 => ['BODY[HEADER]' => $this->header('Old', '11:59:59')],
                2 => ['BODY[HEADER]' => $this->header('Boundary', '12:00:00')],
                3 => ['BODY[HEADER]' => $this->header('Newest', '13:00:00')],
            ]));
        $protocol->shouldReceive('fetch')->once()->with(['UID', 'BODY.PEEK[]'], [3], null, IMAP::ST_UID)
            ->andReturn($this->response([3 => ['BODY[]' => $this->header('Newest', '13:00:00')."\r\nHello newsletter"]]));

        $messages = (new WebklexMailbox($clients))->messagesSince('mail.example.test', '93.184.216.34', 'reader', 'secret', 'Newsletters', CarbonImmutable::parse('2026-11-15 12:00:00 UTC'), 1);

        $this->assertCount(1, $messages);
        $this->assertSame('Newest', $messages[0]->subject);
        $this->assertSame('<issue@example.test>', $messages[0]->messageId);
        $this->assertSame('Hello newsletter', $messages[0]->text);
        $this->assertSame(3, $messages[0]->uid);
        $this->assertSame('42', $messages[0]->uidValidity);
    }

    public function test_reports_missing_folders_and_disconnects(): void
    {
        $clients = Mockery::mock(ClientManager::class);
        $client = Mockery::mock(Client::class);
        $clients->shouldReceive('make')->once()->andReturn($client);
        $client->shouldReceive('connect')->once()->andReturnSelf();
        $client->shouldReceive('getFolderByPath')->once()->with('Missing')->andReturnNull();
        $client->shouldReceive('disconnect')->once()->andReturnSelf();

        $this->expectException(FolderNotFoundException::class);

        (new WebklexMailbox($clients))->messagesSince('mail.example.test', '93.184.216.34', 'reader', 'secret', 'Missing', CarbonImmutable::now(), 20);
    }

    public function test_empty_search_results_skip_fetching_bodies_and_disconnect(): void
    {
        $clients = Mockery::mock(ClientManager::class);
        $client = Mockery::mock(Client::class);
        $protocol = Mockery::mock(ImapProtocol::class);
        $folder = Mockery::mock(Folder::class);
        $clients->shouldReceive('make')->once()->andReturn($client);
        $client->shouldReceive('connect')->once()->andReturnSelf();
        $client->shouldReceive('getFolderByPath')->once()->with('INBOX')->andReturn($folder);
        $folder->shouldReceive('examine')->once()->andReturn(['uidvalidity' => 42]);
        $client->shouldReceive('getConnection')->once()->andReturn($protocol);
        $client->shouldReceive('disconnect')->once()->andReturnSelf();
        $protocol->shouldReceive('search')->once()->andReturn($this->response([]));

        $this->assertSame([], (new WebklexMailbox($clients))->messagesSince(
            'mail.example.test', '93.184.216.34', 'reader', 'secret', 'INBOX', CarbonImmutable::parse('2026-11-15'), 20));
    }

    public function test_connection_failure_still_disconnects_and_preserves_the_error(): void
    {
        $clients = Mockery::mock(ClientManager::class);
        $client = Mockery::mock(Client::class);
        $clients->shouldReceive('make')->once()->andReturn($client);
        $client->shouldReceive('connect')->once()->andThrow(new AuthFailedException);
        $client->shouldReceive('disconnect')->once()->andReturnSelf();

        $this->expectException(AuthFailedException::class);

        (new WebklexMailbox($clients))->messagesSince('mail.example.test', '93.184.216.34', 'reader', 'secret', 'INBOX', CarbonImmutable::now(), 20);
    }

    private function header(string $subject, string $time): string
    {
        return "Date: Sun, 15 Nov 2026 {$time} +0000\r\nSubject: {$subject}\r\nMessage-ID: <issue@example.test>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    }

    private function response(mixed $data): Response
    {
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('validatedData')->once()->andReturn($data);

        return $response;
    }
}
