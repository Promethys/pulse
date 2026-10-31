<?php

declare(strict_types=1);

namespace App\Digest\Discord;

use RuntimeException;
use Throwable;

final class DiscordException extends RuntimeException
{
    public function __construct(string $message = '', public readonly ?int $status = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
