<?php

declare(strict_types=1);

namespace App\Digest\Discord;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/** The few Discord REST calls Pulse needs, as a bot. */
final class DiscordClient
{
    private const API = 'https://discord.com/api/v10';

    private const NO_MENTIONS = ['parse' => []];

    /**
     * @return array<string, mixed>
     */
    public function channel(string $channelId): array
    {
        return $this->send('get', "/channels/{$channelId}")->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function guild(string $guildId): array
    {
        return $this->send('get', "/guilds/{$guildId}")->json();
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function forumChannels(string $guildId): array
    {
        $channels = $this->tagList($this->send('get', "/guilds/{$guildId}/channels")->json());
        $forums = array_filter($channels, fn (array $channel): bool => ($channel['type'] ?? null) === 15);
        usort($forums, fn (array $first, array $second): int => ($first['position'] ?? 0) <=> ($second['position'] ?? 0));

        return array_map(fn (array $channel): array => [
            'id' => (string) $channel['id'],
            'name' => (string) $channel['name'],
        ], $forums);
    }

    /**
     * @return list<array<string, mixed>> the forum's tags, unchanged (id, name, emoji…)
     */
    public function forumTags(string $channelId): array
    {
        return $this->tagList($this->send('get', "/channels/{$channelId}")->json('available_tags'));
    }

    /**
     * Replaces the forum's tag list (existing tags must be sent back as they are).
     *
     * @param  list<array<string, mixed>>  $tags
     * @return list<array<string, mixed>>
     */
    public function setForumTags(string $channelId, array $tags): array
    {
        return $this->tagList($this->send('patch', "/channels/{$channelId}", ['available_tags' => $tags])->json('available_tags'));
    }

    /**
     * @param  list<string>  $tagIds
     * @return string the new thread's id
     */
    public function createForumPost(string $channelId, string $title, string $content, array $tagIds): string
    {
        $id = $this->send('post', "/channels/{$channelId}/threads", [
            'name' => $title,
            'message' => ['content' => $content, 'allowed_mentions' => self::NO_MENTIONS],
            'applied_tags' => $tagIds,
        ])->json('id');

        if (! is_string($id) || $id === '') {
            throw new DiscordException('Discord did not return the new thread id');
        }

        return $id;
    }

    public function postMessage(string $threadId, string $content): void
    {
        $this->send('post', "/channels/{$threadId}/messages", ['content' => $content, 'allowed_mentions' => self::NO_MENTIONS]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function send(string $method, string $path, array $body = [], bool $retried = false): Response
    {
        $token = config('services.discord.token');

        if (! is_string($token) || $token === '') {
            throw new DiscordException('DISCORD_BOT_TOKEN is not set');
        }

        try {
            $response = Http::withHeaders(['Authorization' => "Bot {$token}"])->acceptJson()->timeout(15)
                ->{$method}(self::API.$path, $body);
        } catch (ConnectionException $e) {
            throw new DiscordException("Discord connection failed: {$e->getMessage()}", previous: $e);
        }

        if ($response->status() === 429 && ! $retried) {
            $wait = $response->json('retry_after') ?? $response->header('Retry-After');
            Sleep::for((int) round(min((float) (is_numeric($wait) ? $wait : 1), 30) * 1000))->milliseconds();

            return $this->send($method, $path, $body, retried: true);
        }

        if ($response->failed()) {
            $message = $response->json('message') ?? mb_substr($response->body(), 0, 200);

            throw new DiscordException("Discord HTTP {$response->status()} on {$method} {$path}: {$message}");
        }

        return $response;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tagList(mixed $tags): array
    {
        return array_values(array_filter(is_array($tags) ? $tags : [], is_array(...)));
    }
}
