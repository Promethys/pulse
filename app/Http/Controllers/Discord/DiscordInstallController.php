<?php

declare(strict_types=1);

namespace App\Http\Controllers\Discord;

use App\Digest\Discord\DiscordException;
use App\Digest\Discord\DiscordInstall;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DiscordInstallController extends Controller
{
    public function install(Request $request, DiscordInstall $discord): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('discord_install_state', $state);

        return redirect()->away($discord->authorizeUrl($state));
    }

    public function callback(Request $request, DiscordInstall $discord): RedirectResponse
    {
        $expectedState = $request->session()->pull('discord_install_state');
        $state = $request->query('state');

        abort_unless(is_string($state) && $state !== '' && is_string($expectedState) && hash_equals($expectedState, $state), 403);

        if ($request->query->has('error')) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Pulse was not added to Discord.')]);

            return to_route('dashboard');
        }

        $code = $request->query('code');

        try {
            $guild = $discord->exchange(is_string($code) ? $code : '');
        } catch (DiscordException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Pulse was not added to Discord.')]);

            return to_route('dashboard');
        }

        $request->user()->discordGuilds()->updateOrCreate(['guild_id' => $guild['id']], ['name' => $guild['name']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pulse was added to :name.', ['name' => $guild['name']])]);

        return to_route('dashboard');
    }
}
