<?php

declare(strict_types=1);

namespace App\Digest\Discord;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class DiscordInstall
{
    public const PERMISSIONS = '274877975568';

    public function authorizeUrl(string $state): string
    {
        return 'https://discord.com/oauth2/authorize?'.http_build_query([
            'client_id' => config('services.discord.client_id'),
            'scope' => 'bot',
            'permissions' => self::PERMISSIONS,
            'response_type' => 'code',
            'redirect_uri' => route('discord.callback'),
            'state' => $state,
        ]);
    }

    /**
     * @return array{id: string, name: string}
     */
    public function exchange(string $code): array
    {
        try {
            $response = Http::asForm()->timeout(15)->post('https://discord.com/api/v10/oauth2/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => route('discord.callback'),
                'client_id' => config('services.discord.client_id'),
                'client_secret' => config('services.discord.client_secret'),
            ]);
        } catch (ConnectionException $e) {
            throw new DiscordException("Discord connection failed: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            throw new DiscordException("Discord HTTP {$response->status()} on token exchange");
        }

        $id = $response->json('guild.id');
        $name = $response->json('guild.name');

        if (! is_string($id) || $id === '') {
            throw new DiscordException('Discord did not return the installed guild id');
        }

        return ['id' => $id, 'name' => is_string($name) && $name !== '' ? $name : $id];
    }
}
