<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SourceProposalOrigin: string implements HasLabel
{
    case Scout = 'scout';
    case User = 'user';
    case Check = 'check';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scout => 'Scout',
            self::User => 'User',
            self::Check => 'Check',
        };
    }
}
