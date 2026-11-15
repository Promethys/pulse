<?php

declare(strict_types=1);

namespace App\Digest\Sources\Imap;

use Carbon\CarbonImmutable;

interface Mailbox
{
    /** @return list<ImapMessage> */
    public function messagesSince(string $host, string $ip, string $username, string $password, string $folder, CarbonImmutable $since, int $limit): array;
}
