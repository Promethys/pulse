<?php

declare(strict_types=1);

namespace App\Digest\Ai;

use RuntimeException;

/** No provider of the chain could answer; callers fall back to output without AI. */
final class AiUnavailableException extends RuntimeException {}
