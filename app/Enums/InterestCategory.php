<?php

declare(strict_types=1);

namespace App\Enums;

enum InterestCategory: string
{
    case Technology = 'technology';
    case SoftwareDevelopment = 'software_development';
    case Life = 'life';
    case SelfImprovement = 'self_improvement';
    case Work = 'work';
    case Media = 'media';
    case Society = 'society';
    case Culture = 'culture';
    case World = 'world';

    public function label(): string
    {
        return match ($this) {
            self::Life => 'Life',
            self::SelfImprovement => 'Self improvement',
            self::Work => 'Work',
            self::Technology => 'Technology',
            self::SoftwareDevelopment => 'Software development',
            self::Media => 'Media',
            self::Society => 'Society',
            self::Culture => 'Culture',
            self::World => 'World',
        };
    }
}
