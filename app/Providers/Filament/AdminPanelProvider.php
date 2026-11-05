<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Http\Middleware\ForceFullPageLoad;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Pulse admin')
            ->brandLogo(fn () => view('filament.brand'))
            ->favicon(asset('favicon.svg').'?v=2')
            ->colors([
                'primary' => [
                    50 => 'oklch(0.975 0.012 230)',
                    100 => 'oklch(0.95 0.025 230)',
                    200 => 'oklch(0.9 0.05 230)',
                    300 => 'oklch(0.84 0.065 230)',
                    400 => 'oklch(0.76 0.08 230)',
                    500 => 'oklch(0.64 0.09 232)',
                    600 => 'oklch(0.5 0.09 235)',
                    700 => 'oklch(0.43 0.08 238)',
                    800 => 'oklch(0.37 0.07 240)',
                    900 => 'oklch(0.3 0.06 240)',
                    950 => 'oklch(0.22 0.045 240)',
                ],
                'gray' => Color::Stone,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->userMenuItems([
                Action::make('backToPulse')
                    ->label(__('Back to Pulse'))
                    ->icon(Heroicon::OutlinedArrowLeft)
                    ->url(fn (): string => route('dashboard')),
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<style>.fi-modal-heading, .fi-modal-description, .fi-tooltip, .tippy-content { overflow-wrap: anywhere; }</style>',
            )
            ->middleware([
                ForceFullPageLoad::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
