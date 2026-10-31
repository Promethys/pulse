<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\DisconnectDiscordGuild;
use App\Actions\DiscoverDiscordForums;
use App\Actions\SaveDeliveryChannel;
use App\Actions\TestDeliveryChannel;
use App\Enums\DeliveryChannelType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveDeliveryChannelRequest;
use App\Http\Resources\DeliveryChannelResource;
use App\Http\Resources\DiscordGuildResource;
use App\Models\DeliveryChannel;
use App\Models\DiscordGuild;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class DeliveryController extends Controller
{
    public function index(Request $request, DiscoverDiscordForums $forums): Response
    {
        $user = $request->user();
        $channels = $user->deliveryChannels()->with('latestDelivery')->get();
        $guilds = $user->discordGuilds()->get()->map(fn (DiscordGuild $guild) => [
            ...(new DiscordGuildResource($guild))->resolve($request),
            ...$forums->handle($guild->guild_id),
            'destinations' => DeliveryChannelResource::collection($channels->filter(fn (DeliveryChannel $channel) => $channel->type === DeliveryChannelType::DiscordBot && ($channel->settings['guild_id'] ?? null) === $guild->guild_id
            )->values())->resolve($request),
        ])->all();
        $email = $channels->firstWhere('type', DeliveryChannelType::Email);

        return Inertia::render('settings/Delivery', [
            'guilds' => $guilds,
            'webhooks' => DeliveryChannelResource::collection($channels->where('type', DeliveryChannelType::DiscordWebhook)->values())->resolve($request),
            'email' => $email === null ? null : (new DeliveryChannelResource($email))->resolve($request),
            'install_url' => config('services.discord.client_id') ? route('discord.install') : null,
            'account_email' => $user->email,
        ]);
    }

    public function store(SaveDeliveryChannelRequest $request, SaveDeliveryChannel $action): RedirectResponse
    {
        /** @var array{type: string, kinds: list<string>, guild_id?: string, channel_id?: string, name?: string, webhook_url?: string, address?: string} $data */
        $data = $request->validated();
        $action->handle($request->user(), $data);

        return back();
    }

    public function update(SaveDeliveryChannelRequest $request, DeliveryChannel $channel, SaveDeliveryChannel $action): RedirectResponse
    {
        Gate::authorize('update', $channel);
        /** @var array{enabled: bool, kinds: list<string>, channel_id?: string, name?: string, webhook_url?: string, address?: string} $data */
        $data = $request->validated();
        $action->handle($request->user(), $data, $channel);

        return back();
    }

    public function destroy(Request $request, DeliveryChannel $channel): RedirectResponse
    {
        Gate::authorize('delete', $channel);
        abort_if($channel->type === DeliveryChannelType::InApp, 403);
        $request->user()->deliveryChannels()->whereKey($channel->id)->delete();

        return back();
    }

    public function test(Request $request, DeliveryChannel $channel, TestDeliveryChannel $action): JsonResponse
    {
        Gate::authorize('update', $channel);
        abort_if($channel->type === DeliveryChannelType::InApp, 403);

        return response()->json($action->handle($channel));
    }

    public function destroyGuild(Request $request, DiscordGuild $guild, DisconnectDiscordGuild $action): RedirectResponse
    {
        abort_unless($request->user()->id === $guild->user_id, 403);
        $action->handle($request->user(), $guild);

        return back();
    }
}
