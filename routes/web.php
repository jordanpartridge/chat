<?php

use App\Http\Controllers\AgentSwarmController;
use App\Http\Controllers\ArtifactController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatStreamController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function (Request $request) {
    $user = $request->user();
    $recentChats = $user->chats()->orderByDesc('updated_at')->limit(5)->get();

    return Inertia::render('Dashboard', [
        'recentChats' => $recentChats,
        'models' => $user->availableModels(),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('chats', [ChatController::class, 'index'])->name('chats.index');
    Route::post('chats', [ChatController::class, 'store'])->name('chats.store');
    Route::get('chats/{chat}', [ChatController::class, 'show'])->name('chats.show');
    Route::patch('chats/{chat}', [ChatController::class, 'update'])->name('chats.update');
    Route::delete('chats/{chat}', [ChatController::class, 'destroy'])->name('chats.destroy');
    Route::post('chats/{chat}/stream', ChatStreamController::class)
        ->middleware('throttle:30,1')
        ->name('chats.stream');

    Route::get('chats/{chat}/artifacts', [ArtifactController::class, 'index'])->name('artifacts.index');
    Route::get('artifacts/{artifact}', [ArtifactController::class, 'show'])->name('artifacts.show');
    Route::get('artifacts/{artifact}/render', [ArtifactController::class, 'render'])->name('artifacts.render');

    // Agent Swarm routes
    Route::get('agents/swarms', [AgentSwarmController::class, 'index'])->name('agents.swarms.index');
    Route::post('agents/swarms', [AgentSwarmController::class, 'store'])->name('agents.swarms.store');
    Route::get('agents/swarms/{swarm}', [AgentSwarmController::class, 'show'])->name('agents.swarms.show');
    Route::post('agents/swarms/status', [AgentSwarmController::class, 'updateAgentStatus'])->name('agents.swarms.update-status');
});

require __DIR__.'/settings.php';

// Dev-only auto-login route
if (app()->environment('local')) {
    Route::get('dev/login/{user?}', function (?App\Models\User $user = null) {
        $user ??= App\Models\User::first() ?? App\Models\User::factory()->create([
            'name' => 'Dev User',
            'email' => 'dev@local.test',
        ]);
        auth()->login($user);

        return redirect()->route('dashboard');
    })->name('dev.login');
}
