<?php

declare(strict_types=1);

namespace App\Digest\Sources\Imap;

use Carbon\CarbonImmutable;

final readonly class ImapMessage
{
    public function __construct(
        public ?string $messageId,
        public int $uid,
        public string $subject,
        public string $html,
        public string $text,
        public CarbonImmutable $date,
        public string $uidValidity,
    ) {}
}
