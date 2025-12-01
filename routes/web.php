<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatStreamController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    $recentChats = auth()->user()->chats()->orderByDesc('updated_at')->limit(5)->get();
    $models = \App\Enums\ModelName::getAvailableModels();

    return Inertia::render('Dashboard', [
        'recentChats' => $recentChats,
        'models' => $models,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('chats', [ChatController::class, 'index'])->name('chats.index');
    Route::post('chats', [ChatController::class, 'store'])->name('chats.store');
    Route::get('chats/{chat}', [ChatController::class, 'show'])->name('chats.show');
    Route::delete('chats/{chat}', [ChatController::class, 'destroy'])->name('chats.destroy');
    Route::post('chats/{chat}/stream', ChatStreamController::class)->name('chats.stream');
});

require __DIR__.'/settings.php';
