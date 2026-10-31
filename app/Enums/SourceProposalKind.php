<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SourceProposalKind: string implements HasLabel
{
    case NewSource = 'new_source';
    case UrlChange = 'url_change';

    public function getLabel(): string
    {
        return match ($this) {
            self::NewSource => 'New source',
            self::UrlChange => 'URL change',
        };
    }
}
