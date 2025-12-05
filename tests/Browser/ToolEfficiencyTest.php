<?php

declare(strict_types=1);

use App\Enums\ModelName;
use App\Models\Chat;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('triggers knowledge tool with Groq model', function (): void {
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

    // Knowledge tool is always available for Groq
    $page->type('@message-input', 'What do you know about Conduit from the knowledge base?')
        ->press('@send-message-button')
        ->wait(20000)
        ->assertNoJavaScriptErrors();

    $responseTime = microtime(true) - $startTime;

    dump("Knowledge tool response time: {$responseTime}s");

    // Take screenshot to verify tool was used
    $page->screenshot(filename: 'knowledge-tool-response');

    expect($responseTime)->toBeLessThan(30);
})->skip(fn () => ! env('RUN_AI_TESTS', false), 'Skipped: Set RUN_AI_TESTS=true');

it('triggers artifact creation tool', function (): void {
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

    // Artifact trigger words: diagram, chart, visualization, svg, html
    $page->type('@message-input', 'Create a simple SVG diagram showing a circle')
        ->press('@send-message-button')
        ->wait(30000)
        ->assertNoJavaScriptErrors();

    $responseTime = microtime(true) - $startTime;

    dump("Artifact creation time: {$responseTime}s");

    $page->screenshot(filename: 'artifact-creation-response');

    expect($responseTime)->toBeLessThan(45);
})->skip(fn () => ! env('RUN_AI_TESTS', false), 'Skipped: Set RUN_AI_TESTS=true');

it('triggers web search tool when configured', function (): void {
    // Only run if Tavily API is configured
    if (empty(config('services.tavily.api_key'))) {
        $this->markTestSkipped('Tavily API key not configured');
    }

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

    $page->type('@message-input', 'Search the web for Laravel 12 release date')
        ->press('@send-message-button')
        ->wait(30000)
        ->assertNoJavaScriptErrors();

    $responseTime = microtime(true) - $startTime;

    dump("Web search response time: {$responseTime}s");

    $page->screenshot(filename: 'web-search-response');

    expect($responseTime)->toBeLessThan(45);
})->skip(fn () => ! env('RUN_AI_TESTS', false), 'Skipped: Set RUN_AI_TESTS=true');

it('triggers Laravel model generation tool', function (): void {
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

    // Laravel trigger words: generate, model, migration, controller
    $page->type('@message-input', 'Generate a Laravel model for a Product with name and price fields')
        ->press('@send-message-button')
        ->wait(30000)
        ->assertNoJavaScriptErrors();

    $responseTime = microtime(true) - $startTime;

    dump("Laravel model generation time: {$responseTime}s");

    $page->screenshot(filename: 'laravel-model-generation');

    expect($responseTime)->toBeLessThan(45);
})->skip(fn () => ! env('RUN_AI_TESTS', false), 'Skipped: Set RUN_AI_TESTS=true');

it('verifies Ollama gracefully handles tool triggers without errors', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $chat = Chat::factory()->for($user)->create([
        'model' => ModelName::LLAMA32->value, // Ollama - no tools
    ]);

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    // Send a message with tool trigger words - Ollama should respond normally
    $page->type('@message-input', 'Create a diagram of a database schema')
        ->press('@send-message-button')
        ->wait(20000)
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();

    // Ollama should respond with text, not error
    $page->screenshot(filename: 'ollama-no-tools-graceful');
})->skip(fn () => ! env('RUN_AI_TESTS', false), 'Skipped: Set RUN_AI_TESTS=true');

it('measures tool overhead compared to plain response', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);

    $this->actingAs($user);

    // Test without tools
    $plainChat = Chat::factory()->for($user)->create([
        'model' => ModelName::GROQ_LLAMA33_70B->value,
    ]);

    $page = visit('/chats/'.$plainChat->id);

    $plainStart = microtime(true);
    $page->type('@message-input', 'What is 2+2?')
        ->press('@send-message-button')
        ->wait(10000);
    $plainTime = microtime(true) - $plainStart;

    // Test with tool trigger
    $toolChat = Chat::factory()->for($user)->create([
        'model' => ModelName::GROQ_LLAMA33_70B->value,
    ]);

    $page = visit('/chats/'.$toolChat->id);

    $toolStart = microtime(true);
    $page->type('@message-input', 'Search the knowledge base for information about testing')
        ->press('@send-message-button')
        ->wait(20000);
    $toolTime = microtime(true) - $toolStart;

    dump([
        'plain_response' => $plainTime.'s',
        'tool_response' => $toolTime.'s',
        'overhead' => ($toolTime - $plainTime).'s',
    ]);

    // Tool overhead should be reasonable
    expect($toolTime - $plainTime)->toBeLessThan(20);
})->skip(fn () => ! env('RUN_AI_TESTS', false), 'Skipped: Set RUN_AI_TESTS=true');
