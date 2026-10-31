<?php

declare(strict_types=1);

namespace App\Enums;

enum SourceProposalOrigin: string
{
    case Scout = 'scout';
    case User = 'user';
    case Check = 'check';
}
