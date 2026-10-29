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
    case ZAi = 'zai';

    public function label(): string
    {
        return match ($this) {
            self::Gemini => 'Gemini',
            self::Anthropic => 'Anthropic',
            self::OpenAi => 'OpenAI',
            self::Groq => 'Groq',
            self::OpenRouter => 'OpenRouter',
            self::ZAi => 'Z.AI',
        };
    }

    public function keyUrl(): string
    {
        return match ($this) {
            self::Gemini => 'https://aistudio.google.com/apikey',
            self::Anthropic => 'https://console.anthropic.com/settings/keys',
            self::OpenAi => 'https://platform.openai.com/api-keys',
            self::Groq => 'https://console.groq.com/keys',
            self::OpenRouter => 'https://openrouter.ai/keys',
            self::ZAi => 'https://z.ai/manage-apikey/apikey-list',
        };
    }

    /**
     * @return list<string>
     */
    public function suggestedModels(): array
    {
        return config("digest.suggested_models.{$this->value}");
    }
}
