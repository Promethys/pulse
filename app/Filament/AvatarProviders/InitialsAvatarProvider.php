<?php

declare(strict_types=1);

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

final class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $initials = str(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->join('');
        $text = htmlspecialchars($initials, ENT_XML1);
        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#bde5f9"/><text x="32" y="33" dominant-baseline="central" text-anchor="middle" font-family="sans-serif" font-size="26" fill="#0b3a52">{$text}</text></svg>
            SVG;

        return 'data:image/svg+xml,'.rawurlencode($svg);
    }
}
