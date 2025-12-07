<?php

use App\Http\Controllers\Api\ProviderValidationController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ProviderCredentialController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::get('settings/providers', [ProviderCredentialController::class, 'index'])->name('provider-credentials.index');
    Route::post('settings/providers', [ProviderCredentialController::class, 'store'])->name('provider-credentials.store');
    Route::post('settings/providers/validate', [ProviderValidationController::class, 'validate'])->name('provider-credentials.validate');
    Route::patch('settings/providers/{credential}', [ProviderCredentialController::class, 'update'])->name('provider-credentials.update');
    Route::delete('settings/providers/{credential}', [ProviderCredentialController::class, 'destroy'])->name('provider-credentials.destroy');
    Route::patch('settings/providers/{credential}/toggle', [ProviderCredentialController::class, 'toggle'])->name('provider-credentials.toggle');
    Route::patch('settings/providers/{credential}/models/{model}/toggle', [ProviderCredentialController::class, 'toggleModel'])->name('provider-credentials.toggle-model');
});
