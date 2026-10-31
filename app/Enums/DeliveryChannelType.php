<?php

declare(strict_types=1);

namespace App\Enums;

use App\Digest\Delivery\Channel;
use App\Digest\Delivery\DiscordBotChannel;
use App\Digest\Delivery\DiscordWebhookChannel;
use App\Digest\Delivery\EmailChannel;
use App\Digest\Delivery\InAppChannel;

enum DeliveryChannelType: string
{
    case DiscordBot = 'discord_bot';
    case DiscordWebhook = 'discord_webhook';
    case Email = 'email';
    case InApp = 'in_app';

    /**
     * @return class-string<Channel>
     */
    public function driver(): string
    {
        return match ($this) {
            self::DiscordBot => DiscordBotChannel::class,
            self::DiscordWebhook => DiscordWebhookChannel::class,
            self::Email => EmailChannel::class,
            self::InApp => InAppChannel::class,
        };
    }
}
