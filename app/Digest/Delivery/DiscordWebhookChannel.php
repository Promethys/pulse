<?php

declare(strict_types=1);

namespace App\Digest\Delivery;

use App\Digest\Rendering\RenderedPost;
use App\Models\Delivery;
use App\Models\Post;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class DiscordWebhookChannel implements Channel
{
    private const MAX_TITLE = 100;

    private const NO_MENTIONS = ['parse' => []];

    public function deliver(Post $post, Delivery $delivery): void
    {
        $rendered = RenderedPost::fromArray($post->rendered ?? []);

        if ($delivery->external_id === null) {
            $threadId = $this->send($delivery, [
                'thread_name' => mb_substr($rendered->title, 0, self::MAX_TITLE),
                'content' => $rendered->messages[0],
                'allowed_mentions' => self::NO_MENTIONS,
            ])->json('channel_id');

            if (! is_string($threadId) || $threadId === '') {
                throw new DeliveryException('Discord webhook did not return the new thread id');
            }

            $delivery->update(['external_id' => $threadId, 'progress' => 1]);
        }

        for ($i = $delivery->progress; $i < count($rendered->messages); $i++) {
            $this->send($delivery, [
                'content' => $rendered->messages[$i],
                'allowed_mentions' => self::NO_MENTIONS,
            ]);
            $delivery->update(['progress' => $i + 1]);
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function send(Delivery $delivery, array $body): Response
    {
        $url = ($delivery->channel->settings['webhook_url'] ?? '').'?wait=true';
        if ($delivery->external_id !== null) {
            $url .= '&thread_id='.rawurlencode($delivery->external_id);
        }

        try {
            $response = Http::timeout(15)->post($url, $body);
        } catch (ConnectionException) {
            throw new DeliveryException('Discord webhook connection failed');
        }

        if ($response->failed()) {
            if ($response->status() === 404) {
                $delivery->channel->update(['enabled' => false]);
            }

            $message = $response->json('message');
            $message = is_string($message) ? $message : 'Request failed';

            throw new DeliveryException("Discord webhook HTTP {$response->status()}: {$message}");
        }

        return $response;
    }
}
