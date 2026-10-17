<?php

declare(strict_types=1);

namespace App\Enums;

enum PostKind: string
{
    case News = 'news';
    case Learning = 'learning';
}
