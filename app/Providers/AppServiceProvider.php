<?php

declare(strict_types=1);

namespace App\Providers;

use App\Digest\Sources\Imap\Mailbox;
use App\Digest\Sources\Imap\WebklexMailbox;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Webklex\PHPIMAP\ClientManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ClientManager::class, fn () => new ClientManager([
            'decoding' => [
                'options' => [
                    'header' => 'mimeheader',
                    'message' => 'mimeheader',
                    'attachment' => 'mimeheader',
                ],
            ],
        ]));
        $this->app->bind(Mailbox::class, WebklexMailbox::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('digest-run', fn (Request $request) => Limit::perMinutes(10, 1)
            ->by((string) $request->user()?->id)
            ->response(fn () => back()->withErrors(['kind' => __('Please wait 10 minutes before trying again.')])));
        RateLimiter::for('suggestions', fn (Request $request) => Limit::perMinute(6)->by((string) $request->user()?->id));
        RateLimiter::for('delivery-test', fn (Request $request) => [
            Limit::perMinute(6)->by('minute:'.$request->user()?->id),
            Limit::perHour(20)->by('hour:'.$request->user()?->id),
        ]);

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    public static function passwordHint(): string
    {
        return app()->isProduction()
            ? 'Use at least 12 characters, including uppercase and lowercase letters, a number and a symbol. Choose a password that has not appeared in a data breach.'
            : 'Use at least 8 characters.';
    }
}
