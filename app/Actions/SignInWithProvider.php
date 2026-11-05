<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Features;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as OAuthUser;
use Throwable;

final class SignInWithProvider
{
    public function handle(Request $request, string $provider): RedirectResponse
    {
        $authenticatedUser = $request->user();
        $link = $request->session()->pull('social.link');
        $linking = is_array($link) && $link['provider'] === $provider;

        if ($linking && ($authenticatedUser === null || $link['user_id'] !== $authenticatedUser->id || ! $authenticatedUser->hasVerifiedEmail())) {
            return to_route('login')->withErrors(['email' => $this->errorMessage($provider)]);
        }

        if ($authenticatedUser !== null && ! $linking) {
            return to_route('dashboard');
        }

        $route = $linking ? 'security.edit' : 'login';
        $errorKey = $linking ? 'social' : 'email';

        try {
            $identity = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return to_route($route)->withErrors([$errorKey => $this->errorMessage($provider)]);
        }

        try {
            if ($linking) {
                DB::transaction(fn () => $this->linkUser($authenticatedUser, $provider, $identity));

                return to_route('security.edit');
            }

            $user = DB::transaction(fn (): User => $this->resolveUser($provider, $identity));
        } catch (UniqueConstraintViolationException) {
            return to_route($route)->withErrors([$errorKey => $this->errorMessage($provider)]);
        } catch (ValidationException $exception) {
            return to_route($route)->withErrors($exception->errors());
        }

        if ($user->two_factor_secret && $user->two_factor_confirmed_at !== null) {
            $request->session()->put(['login.id' => $user->id, 'login.remember' => true]);

            TwoFactorAuthenticationChallenged::dispatch($user);

            return to_route('two-factor.login');
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended((string) config('fortify.home'));
    }

    private function linkUser(User $user, string $provider, ProviderUser $identity): void
    {
        $user = User::query()->lockForUpdate()->findOrFail($user->id);
        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $identity->getId())
            ->first();

        if ($account !== null && $account->user_id !== $user->id) {
            throw ValidationException::withMessages(['social' => __('This provider account is already connected to another reader.')]);
        }

        $user->socialAccounts()->updateOrCreate(
            ['provider' => $provider],
            ['provider_id' => $identity->getId()],
        );
    }

    private function resolveUser(string $provider, ProviderUser $identity): User
    {
        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $identity->getId())
            ->first();

        if ($account !== null) {
            return $account->user;
        }

        $email = $identity->getEmail();

        $verified = $provider !== 'google' || ($identity instanceof OAuthUser && ($identity->getRaw()['email_verified'] ?? null) === true);

        if (! is_string($email) || trim($email) === '' || ! $verified) {
            throw ValidationException::withMessages(['email' => $this->errorMessage($provider)]);
        }

        $email = Str::lower(trim($email));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            if (! Features::enabled(Features::registration())) {
                throw ValidationException::withMessages(['email' => __('Registration is closed on this instance.')]);
            }

            $user = User::query()->create([
                'name' => $identity->getName() ?: ($identity->getNickname() ?: Str::before($email, '@')),
                'email' => $email,
                'password' => null,
            ]);

            app(InitializeUserDigest::class)->handle($user);
        }

        if ($user->email_verified_at === null && ! $user->wasRecentlyCreated) {
            $this->revokeUnprovenAccess($user);
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $identity->getId(),
        ]);

        return $user;
    }

    // Whoever registered this unverified email never proved owning it.
    private function revokeUnprovenAccess(User $user): void
    {
        $user->socialAccounts()->delete();
        $user->passkeys()->delete();

        $user->forceFill([
            'password' => null,
            'remember_token' => Str::random(60),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }

    private function errorMessage(string $provider): string
    {
        return __("Sign-in with :provider didn't work. Try again.", ['provider' => $provider === 'google' ? 'Google' : 'GitHub']);
    }
}
