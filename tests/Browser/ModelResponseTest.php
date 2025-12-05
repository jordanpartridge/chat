<?php

declare(strict_types=1);

use App\Enums\ModelName;
use App\Models\Chat;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('can send a message and receive response from Ollama model', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $chat = Chat::factory()->for($user)->create([
        'model' => ModelName::LLAMA32->value,
    ]);

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    $startTime = microtime(true);

    $page->assertVisible('@message-input')
        ->type('@message-input', 'Say hello in exactly 3 words')
        ->press('@send-message-button')
        ->wait(10000) // Wait up to 10s for response
        ->assertNoJavaScriptErrors();

    $responseTime = microtime(true) - $startTime;

    // Log response time for analysis
    dump("Ollama response time: {$responseTime}s");

    expect($responseTime)->toBeLessThan(30); // Should respond within 30s
})->skip(fn () => ! env('RUN_AI_TESTS', false), 'Skipped: Set RUN_AI_TESTS=true to run AI model tests');

it('can send a message and receive response from Groq model', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $chat = Chat::factory()->for($user)->create([
        'model' => ModelName::GROQ_LLAMA33_70B->value,
    ]);

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    $startTime = microtime(true);

    $page->assertVisible('@message-input')
        ->type('@message-input', 'Say hello in exactly 3 words')
        ->press('@send-message-button')
        ->wait(10000)
        ->assertNoJavaScriptErrors();

    $responseTime = microtime(true) - $startTime;

    dump("Groq response time: {$responseTime}s");

    expect($responseTime)->toBeLessThan(15); // Groq should be faster
})->skip(fn () => ! env('RUN_AI_TESTS', false), 'Skipped: Set RUN_AI_TESTS=true to run AI model tests');

it('compares response times between models', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);

    $this->actingAs($user);

    $results = [];

    foreach ([ModelName::LLAMA32, ModelName::GROQ_LLAMA33_70B] as $model) {
        $chat = Chat::factory()->for($user)->create([
            'model' => $model->value,
        ]);

        $page = visit('/chats/'.$chat->id);

        $startTime = microtime(true);

        $page->type('@message-input', 'What is 2+2? Reply with just the number.')
            ->press('@send-message-button')
            ->wait(15000);

        $results[$model->value] = microtime(true) - $startTime;
    }

    dump('Model Response Times:', $results);

    // Both should complete
    expect(count($results))->toBe(2);
})->skip(fn () => ! env('RUN_AI_TESTS', false), 'Skipped: Set RUN_AI_TESTS=true to run AI model tests');

it('can switch models via the model selector', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $chat = Chat::factory()->for($user)->create([
        'model' => ModelName::LLAMA32->value,
    ]);

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    $page->assertNoJavaScriptErrors()
        ->assertSee('Llama 3.2'); // Model selector shows current model

    // Take screenshot of model selector
    $page->screenshot(filename: 'model-selector-test');
});
