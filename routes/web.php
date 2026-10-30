<?php

declare(strict_types=1);

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\DayController;
use App\Http\Controllers\Discord\DiscordInstallController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TodayController;
use App\Http\Middleware\EnsureOnboarded;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    if ($request->user() === null) {
        return Inertia::render('Welcome');
    }

    return app(EnsureEmailIsVerified::class)->handle($request,
        fn () => app(EnsureOnboarded::class)->handle($request, fn () => app(TodayController::class)($request)->toResponse($request)));
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => redirect('/', 301))->middleware('onboarded')->name('dashboard');
    Route::get('days/{date}', DayController::class)->middleware('onboarded')->name('days.show');
    Route::get('archive', ArchiveController::class)->middleware('onboarded')->name('archive.index');
    Route::get('search', SearchController::class)->middleware('onboarded')->name('search.index');
    Route::get('tags/{tag:slug}', TagController::class)->middleware('onboarded')->name('tags.show');

    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('onboarding/interests', [OnboardingController::class, 'interests'])->name('onboarding.interests');
    Route::put('onboarding/sources', [OnboardingController::class, 'sources'])->name('onboarding.sources');
    Route::put('onboarding/about', [OnboardingController::class, 'about'])->name('onboarding.about');
    Route::post('onboarding/ai-key', [OnboardingController::class, 'aiKey'])->name('onboarding.ai-key');
    Route::post('onboarding/ai-key/test', [OnboardingController::class, 'testAiKey'])
        ->middleware('throttle:6,1')->name('onboarding.ai-key.test');
    Route::post('onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');

    Route::get('discord/install', [DiscordInstallController::class, 'install'])->name('discord.install');
    Route::get('discord/callback', [DiscordInstallController::class, 'callback'])->name('discord.callback');
});

require __DIR__.'/settings.php';
