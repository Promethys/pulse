<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\AiKeysController;
use App\Http\Controllers\Settings\DeliveryController;
use App\Http\Controllers\Settings\DigestController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified', 'onboarded'])->group(function () {
    Route::get('settings/digest', [DigestController::class, 'edit'])->name('digest.edit');
    Route::put('settings/digest', [DigestController::class, 'update'])->name('digest.update');

    Route::get('settings/ai-keys', [AiKeysController::class, 'index'])->name('ai-keys.index');
    Route::post('settings/ai-keys', [AiKeysController::class, 'store'])->name('ai-keys.store');
    Route::put('settings/ai-keys/order', [AiKeysController::class, 'order'])->name('ai-keys.order');
    Route::post('settings/ai-keys/test', [AiKeysController::class, 'test'])->middleware('throttle:6,1')->name('ai-keys.test');
    Route::delete('settings/ai-keys/{aiKey}', [AiKeysController::class, 'destroy'])->name('ai-keys.destroy');
    Route::post('settings/ai-keys/{aiKey}/test', [AiKeysController::class, 'testSaved'])->middleware('throttle:6,1')->name('ai-keys.test-saved');

    Route::get('settings/delivery', [DeliveryController::class, 'index'])->name('delivery.index');
    Route::post('settings/delivery', [DeliveryController::class, 'store'])->name('delivery.store');
    Route::put('settings/delivery/{channel}', [DeliveryController::class, 'update'])->name('delivery.update');
    Route::delete('settings/delivery/{channel}', [DeliveryController::class, 'destroy'])->name('delivery.destroy');
    Route::post('settings/delivery/{channel}/test', [DeliveryController::class, 'test'])->middleware('throttle:6,1')->name('delivery.test');
    Route::delete('settings/delivery/guilds/{guild}', [DeliveryController::class, 'destroyGuild'])->name('delivery.guilds.destroy');

    Route::inertia('settings', 'settings/Index')->name('settings.index');

    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
