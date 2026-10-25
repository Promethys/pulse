<?php

use App\Http\Controllers\Discord\DiscordInstallController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('discord/install', [DiscordInstallController::class, 'install'])->name('discord.install');
    Route::get('discord/callback', [DiscordInstallController::class, 'callback'])->name('discord.callback');
});

require __DIR__.'/settings.php';
