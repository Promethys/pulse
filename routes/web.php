<?php

use App\Http\Controllers\Discord\DiscordInstallController;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    if ($request->user() === null) {
        return Inertia::render('Welcome');
    }

    return app(EnsureEmailIsVerified::class)->handle($request, fn () => Inertia::render('Today'));
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => redirect('/', 301))->name('dashboard');

    Route::get('discord/install', [DiscordInstallController::class, 'install'])->name('discord.install');
    Route::get('discord/callback', [DiscordInstallController::class, 'callback'])->name('discord.callback');
});

require __DIR__.'/settings.php';
