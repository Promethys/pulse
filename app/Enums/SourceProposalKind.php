<?php

declare(strict_types=1);

namespace App\Enums;

enum SourceProposalKind: string
{
    case NewSource = 'new_source';
    case UrlChange = 'url_change';
}
