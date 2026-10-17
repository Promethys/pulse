<?php

declare(strict_types=1);

namespace App\Enums;

enum ItemStatus: string
{
    case Candidate = 'candidate';
    case Selected = 'selected';
    case Skipped = 'skipped';
}
