<?php

use App\Http\Controllers\ArtifactController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatStreamController;
use App\Services\ModelSyncService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function (ModelSyncService $modelSyncService) {
    $recentChats = auth()->user()->chats()->orderByDesc('updated_at')->limit(5)->get();
    $models = $modelSyncService->syncAndGetAvailable();

    return Inertia::render('Dashboard', [
        'recentChats' => $recentChats,
        'models' => $models,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('chats', [ChatController::class, 'index'])->name('chats.index');
    Route::post('chats', [ChatController::class, 'store'])->name('chats.store');
    Route::get('chats/{chat}', [ChatController::class, 'show'])->name('chats.show');
    Route::patch('chats/{chat}', [ChatController::class, 'update'])->name('chats.update');
    Route::delete('chats/{chat}', [ChatController::class, 'destroy'])->name('chats.destroy');
    Route::post('chats/{chat}/stream', ChatStreamController::class)->name('chats.stream');

    Route::get('chats/{chat}/artifacts', [ArtifactController::class, 'index'])->name('artifacts.index');
    Route::get('artifacts/{artifact}', [ArtifactController::class, 'show'])->name('artifacts.show');
    Route::get('artifacts/{artifact}/render', [ArtifactController::class, 'render'])->name('artifacts.render');
});

require __DIR__.'/settings.php';
