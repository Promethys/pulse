<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Actions\DiscoverDiscordForums;
use App\Enums\DeliveryChannelType;
use App\Models\DeliveryChannel;
use App\Rules\DiscordWebhookUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SaveDeliveryChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $channel = $this->route('channel');
        if ($channel instanceof DeliveryChannel) {
            Gate::authorize('update', $channel);

            return $channel->type !== DeliveryChannelType::InApp;
        }

        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $channel = $this->route('channel');
        $updating = $channel instanceof DeliveryChannel;
        $type = $updating ? $channel->type->value : $this->input('type');
        $rules = [
            'kinds' => ['required', 'array', 'min:1'],
            'kinds.*' => ['required', 'string', 'distinct', Rule::in(['news', 'learning'])],
        ];
        if ($updating) {
            $rules['enabled'] = ['required', 'boolean'];
        } else {
            $rules['type'] = ['required', Rule::in(['discord_bot', 'discord_webhook', 'email'])];
        }

        return [...$rules, ...match ($type) {
            'discord_bot' => [
                ...($updating ? [] : ['guild_id' => ['required', 'string', Rule::exists('discord_guilds', 'guild_id')->where('user_id', $this->user()->id)]]),
                'channel_id' => [$updating ? 'sometimes' : 'required', 'string'],
            ],
            'discord_webhook' => [
                'name' => [$updating ? 'sometimes' : 'required', 'string', 'min:1', 'max:100'],
                'webhook_url' => [$updating ? 'sometimes' : 'required', 'string', new DiscordWebhookUrl],
            ],
            'email' => ['address' => [$updating ? 'sometimes' : 'required', 'string', 'email']],
            default => [],
        }];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->has('channel_id')) {
                return;
            }
            $channel = $this->route('channel');
            if ($channel instanceof DeliveryChannel && $channel->type !== DeliveryChannelType::DiscordBot) {
                return;
            }
            if (! $channel instanceof DeliveryChannel && $this->input('type') !== 'discord_bot') {
                return;
            }
            $guildId = $channel instanceof DeliveryChannel ? ($channel->settings['guild_id'] ?? '') : $this->string('guild_id')->toString();
            if (! $this->user()->discordGuilds()->where('guild_id', $guildId)->exists()) {
                $validator->errors()->add('guild_id', __('Choose one of your Discord servers.'));

                return;
            }
            $forums = app(DiscoverDiscordForums::class)->handle($guildId)['forums'];
            if (! in_array($this->input('channel_id'), array_column($forums, 'id'), true)) {
                $validator->errors()->add('channel_id', __('Choose a forum channel from this server.'));
            }
        }];
    }
}
