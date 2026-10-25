<?php

declare(strict_types=1);

namespace App\Enums;

enum AiProvider: string
{
    case Gemini = 'gemini';
    case Anthropic = 'anthropic';
    case OpenAi = 'openai';
    case Groq = 'groq';
    case OpenRouter = 'openrouter';

    public function label(): string
    {
        return match ($this) {
            self::Gemini => 'Gemini',
            self::Anthropic => 'Anthropic',
            self::OpenAi => 'OpenAI',
            self::Groq => 'Groq',
            self::OpenRouter => 'OpenRouter',
        };
    }
}
