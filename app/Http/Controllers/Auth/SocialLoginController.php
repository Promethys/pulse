<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\SignInWithProvider;
use App\Auth\SocialProviders;
use App\Http\Controllers\Controller;
use App\Http\Middleware\RequirePasswordIfPresent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\Response;

final class SocialLoginController extends Controller
{
    public function redirect(Request $request, string $provider, SocialProviders $providers): Response
    {
        abort_unless($providers->isEnabled($provider), 404);

        $request->session()->forget('social.link');

        if ($request->query('intent') !== 'link') {
            return $request->user() === null ? $this->toProvider($provider) : to_route('dashboard');
        }

        $user = $request->user();

        if ($user === null) {
            return to_route('login');
        }

        if (! $user->hasVerifiedEmail()) {
            return to_route('verification.notice');
        }

        return app(RequirePasswordIfPresent::class)->handle($request, function () use ($request, $provider, $user): Response {
            $request->session()->put('social.link', ['provider' => $provider, 'user_id' => $user->id]);

            return $this->toProvider($provider);
        });
    }

    public function unlink(Request $request, string $provider, SocialProviders $providers): RedirectResponse
    {
        abort_unless($providers->isEnabled($provider), 404);

        return DB::transaction(function () use ($request, $provider): RedirectResponse {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $account = $user->socialAccounts()->where('provider', $provider)->first();

            if ($account === null) {
                return to_route('security.edit')->withErrors(['social' => __('This provider account is not connected.')]);
            }

            $availableAccounts = $user->socialAccounts()
                ->whereIn('provider', app(SocialProviders::class)->enabled())
                ->count();

            if (! $user->has_password && $availableAccounts <= 1) {
                return to_route('security.edit')->withErrors(['social' => __('Set a password or connect another account before disconnecting your last sign-in method.')]);
            }

            $account->delete();

            return to_route('security.edit');
        });
    }

    public function callback(Request $request, string $provider, SocialProviders $providers, SignInWithProvider $signIn): RedirectResponse
    {
        abort_unless($providers->isEnabled($provider), 404);

        return $signIn->handle($request, $provider);
    }

    private function toProvider(string $provider): Response
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($provider);

        if ($provider === 'github') {
            $driver->scopes(['user:email']);
        }

        return $driver->redirect();
    }
}
