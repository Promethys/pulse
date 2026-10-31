<?php

declare(strict_types=1);

namespace App\Actions;

use App\Digest\Discord\DiscordClient;
use App\Enums\DeliveryChannelType;
use App\Mail\DeliveryTestMail;
use App\Models\DeliveryChannel;
use App\Rules\DiscordWebhookUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class TestDeliveryChannel
{
    public function __construct(private readonly DiscordClient $discord) {}

    /** @return array{ok: bool, message: string} */
    public function handle(DeliveryChannel $channel): array
    {
        abort_if($channel->type === DeliveryChannelType::InApp, 403);
        $settings = $channel->settings ?? [];
        try {
            switch ($channel->type) {
                case DeliveryChannelType::DiscordBot:
                    $this->discord->createForumPost($settings['channel_id'] ?? '', 'Pulse test', 'Your Pulse destination is ready.', []);
                    break;
                case DeliveryChannelType::DiscordWebhook:
                    $url = $settings['webhook_url'] ?? '';
                    if (preg_match(DiscordWebhookUrl::PATTERN, $url) !== 1) {
                        throw new RuntimeException('Invalid Discord webhook URL');
                    }
                    $response = Http::timeout(15)->post($url.'?wait=true', [
                        'thread_name' => 'Pulse test',
                        'content' => 'Your Pulse destination is ready.',
                        'allowed_mentions' => ['parse' => []],
                    ]);
                    if ($response->failed()) {
                        $message = $response->json('message');
                        throw new RuntimeException(is_string($message) ? $message : 'Discord request failed');
                    }
                    break;
                case DeliveryChannelType::Email:
                    Mail::to($settings['address'] ?? '')->send(new DeliveryTestMail);
                    break;
                case DeliveryChannelType::InApp:
                    abort(403);
            }

            return ['ok' => true, 'message' => __('Test sent.')];
        } catch (Throwable $exception) {
            $secrets = array_filter([$settings['webhook_url'] ?? '', (string) config('services.discord.token')]);
            if (isset($settings['webhook_url'])) {
                $secrets[] = basename($settings['webhook_url']);
            }
            $secrets = array_values(array_unique([...$secrets, ...array_map(rawurlencode(...), $secrets)]));
            Log::warning('Delivery test failed.', [
                'channel_id' => $channel->id,
                'error' => Str::limit(str_replace($secrets, '[redacted]', $exception->getMessage()), 500),
            ]);

            return ['ok' => false, 'message' => __('Could not send a test. Check the destination and try again.')];
        }
    }
}
