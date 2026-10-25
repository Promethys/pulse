<?php

declare(strict_types=1);

namespace App\Enums;

enum DeliveryChannelType: string
{
    case DiscordBot = 'discord_bot';
    case DiscordWebhook = 'discord_webhook';
    case Email = 'email';
    case InApp = 'in_app';
}
