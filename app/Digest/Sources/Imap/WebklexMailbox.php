<?php

declare(strict_types=1);

namespace App\Digest\Sources\Imap;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Connection\Protocols\ImapProtocol;
use Webklex\PHPIMAP\Header;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Message;

final class WebklexMailbox implements Mailbox
{
    public function __construct(private readonly ClientManager $clients) {}

    public function messagesSince(string $host, string $ip, string $username, string $password, string $folder, CarbonImmutable $since, int $limit): array
    {
        $client = $this->clients->make([
            'host' => str_contains($ip, ':') ? '['.$ip.']' : $ip,
            'port' => 993,
            'protocol' => 'imap',
            'encryption' => 'ssl',
            'validate_cert' => true,
            'ssl_options' => ['peer_name' => $host, 'verify_peer' => true, 'verify_peer_name' => true],
            'username' => $username,
            'password' => $password,
            'timeout' => 15,
        ]);

        try {
            $client->connect();
            $mailFolder = $client->getFolderByPath($folder);

            if ($mailFolder === null) {
                throw new FolderNotFoundException;
            }

            $status = $mailFolder->examine();
            $protocol = $client->getConnection();

            if (! $protocol instanceof ImapProtocol) {
                throw new RuntimeException('Unsupported mail protocol.');
            }

            /** @var list<int> $uids */
            $uids = $protocol->search(['SINCE "'.$since->format('d-M-Y').'"'], IMAP::ST_UID)->validatedData();

            if ($uids === [] || $limit <= 0) {
                return [];
            }

            /** @var array<int, array<string, string>> $headers */
            $headers = $protocol->fetch(['UID', 'BODY.PEEK[HEADER]'], $uids, null, IMAP::ST_UID)->validatedData();
            $dated = [];

            foreach ($headers as $uid => $data) {
                $header = new Header($data['BODY[HEADER]'], $client->getConfig());
                $date = $header->get('date')->first();

                if ($date instanceof DateTimeInterface && CarbonImmutable::instance($date)->greaterThanOrEqualTo($since)) {
                    $dated[$uid] = CarbonImmutable::instance($date);
                }
            }

            uasort($dated, fn (CarbonImmutable $a, CarbonImmutable $b) => $b <=> $a);
            $selected = array_keys(array_slice($dated, 0, $limit, true));

            if ($selected === []) {
                return [];
            }

            // Query::leaveUnread restores flags after a normal fetch, so use real PEEK reads.
            /** @var array<int, array<string, string>> $bodies */
            $bodies = $protocol->fetch(['UID', 'BODY.PEEK[]'], $selected, null, IMAP::ST_UID)->validatedData();
            $messages = [];

            foreach ($selected as $uid) {
                $raw = $bodies[$uid]['BODY[]'];
                $message = Message::fromString($raw, $client->getConfig());
                $rawHeader = preg_replace('/\r?\n[ \t]+/', ' ', $headers[$uid]['BODY[HEADER]']) ?? '';
                preg_match('/^Message-ID:[ \t]*([^\r\n]*)/mi', $rawHeader, $messageId);
                $messages[] = new ImapMessage(
                    $messageId[1] ?? null, $uid, $message->getSubject()->toString(),
                    $message->getHTMLBody(), $message->getTextBody(), $dated[$uid], (string) $status['uidvalidity'],
                );
            }

            return $messages;
        } finally {
            try {
                $client->disconnect();
            } catch (Throwable) {
            }
        }
    }
}
