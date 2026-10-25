<?php

declare(strict_types=1);

namespace App\Enums;

enum InterestStatus: string
{
    case Draft = 'draft';
    case Live = 'live';
}
