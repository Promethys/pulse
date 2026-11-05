<?php

declare(strict_types=1);

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\DayController;
use App\Http\Controllers\Discord\DiscordInstallController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\InterestProposalController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SourcesController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TodayController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;

Route::get('auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])
    ->where('provider', 'google|github')->middleware('throttle:social')->name('social.redirect');
Route::get('auth/{provider}/callback', [SocialLoginController::class, 'callback'])
    ->where('provider', 'google|github')->middleware('throttle:social')->name('social.callback');

Route::delete('auth/{provider}/unlink', [SocialLoginController::class, 'unlink'])
    ->where('provider', 'google|github')->middleware(['auth', 'throttle:social', 'password.confirm'])->name('social.unlink');

// Fortify has no limiter option for these routes, so they replace its own.
Route::middleware('guest')->group(function () {
    if (Features::enabled(Features::registration())) {
        Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:register')->name('register.store');
    }

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:forgot-password')->name('password.email');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:reset-password')->name('password.update');
});

Route::inertia('/', 'Welcome')->name('home');

Route::inertia('features', 'Features')->name('features');
Route::inertia('open-source', 'OpenSource')->name('open-source');
Route::inertia('privacy', 'legal/Privacy')->name('privacy');
Route::inertia('terms', 'legal/Terms')->name('terms');
Route::get('docs', [DocsController::class, 'index'])->name('docs.index');
Route::get('docs/{section}/{article}', [DocsController::class, 'show'])
    ->where(['section' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'article' => '[a-z0-9]+(?:-[a-z0-9]+)*'])
    ->name('docs.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', TodayController::class)->middleware('onboarded')->name('dashboard');
    Route::get('days/{date}', DayController::class)->middleware('onboarded')->name('days.show');
    Route::get('archive', ArchiveController::class)->middleware('onboarded')->name('archive.index');
    Route::get('search', SearchController::class)->middleware('onboarded')->name('search.index');
    Route::get('learning', [LearningController::class, 'index'])->middleware('onboarded')->name('learning.index');
    Route::get('learning/{id}', [LearningController::class, 'show'])->whereNumber('id')->middleware('onboarded')->name('learning.show');
    Route::get('tags/{tag:slug}', TagController::class)->middleware('onboarded')->name('tags.show');

    Route::post('interest-proposals', [InterestProposalController::class, 'store'])->middleware('throttle:suggestions')->name('interest-proposals.store');

    Route::middleware('onboarded')->prefix('sources')->name('sources.')->group(function () {
        Route::get('/', [SourcesController::class, 'index'])->name('index');
        Route::put('subscriptions/{source}', [SourcesController::class, 'subscription'])->name('subscription');
        Route::put('interests', [SourcesController::class, 'interests'])->name('interests');
        Route::post('test', [SourcesController::class, 'test'])->middleware('throttle:6,1')->name('test');
        Route::post('/', [SourcesController::class, 'store'])->name('store');
        Route::post('{source}/suggest', [SourcesController::class, 'suggest'])->middleware('throttle:suggestions')->name('suggest');
        Route::put('{source}', [SourcesController::class, 'update'])->name('update');
        Route::delete('{source}', [SourcesController::class, 'destroy'])->name('destroy');
    });

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
