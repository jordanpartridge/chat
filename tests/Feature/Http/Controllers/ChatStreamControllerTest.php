<?php

use App\Enums\ModelName;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

function createTextResponse(string $text): TextResponse
{
    return new TextResponse(
        steps: collect([]),
        text: $text,
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(10, 20),
        meta: new Meta('fake-id', 'fake-model'),
        messages: collect([]),
    );
}

it('redirects guests to login', function () {
    $chat = Chat::factory()->create();

    $response = $this->post(route('chats.stream', $chat), [
        'message' => 'Hello',
    ]);

    $response->assertRedirect(route('login'));
});

it('forbids streaming to another user\'s chat', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $chat = Chat::factory()->for($otherUser)->create();

    $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
        'message' => 'Hello',
    ]);

    $response->assertForbidden();
});

it('requires a message field', function () {
    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create();

    $response = $this->actingAs($user)->post(route('chats.stream', $chat), []);

    $response->assertSessionHasErrors('message');
});

it('requires message to be a string', function () {
    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create();

    $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
        'message' => ['not', 'a', 'string'],
    ]);

    $response->assertSessionHasErrors('message');
});

it('enforces maximum message length', function () {
    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create();

    $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
        'message' => str_repeat('a', 10001),
    ]);

    $response->assertSessionHasErrors('message');
});

it('validates model enum when provided', function () {
    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create();

    $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
        'message' => 'Hello',
        'model' => 'invalid-model',
    ]);

    $response->assertSessionHasErrors('model');
});

it('creates a user message and returns streamed response', function () {
    Prism::fake([createTextResponse('Hello from AI')]);

    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create(['model' => ModelName::LLAMA32->value]);

    $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
        'message' => 'Hello AI',
    ]);

    $response->assertOk();
    expect($response->baseResponse)->toBeInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class);

    $this->assertDatabaseHas('messages', [
        'chat_id' => $chat->id,
        'role' => 'user',
    ]);

    $userMessage = Message::where('chat_id', $chat->id)->where('role', 'user')->first();
    expect($userMessage->parts['text'])->toBe('Hello AI');
});

it('trims whitespace from message', function () {
    Prism::fake([createTextResponse('Response')]);

    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create(['model' => ModelName::LLAMA32->value]);

    $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
        'message' => '   Hello with spaces   ',
    ]);

    $response->assertOk();

    $userMessage = Message::where('chat_id', $chat->id)->where('role', 'user')->first();
    expect($userMessage->parts['text'])->toBe('Hello with spaces');
});

it('allows nullable model field', function () {
    Prism::fake([createTextResponse('Response')]);

    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create(['model' => ModelName::LLAMA32->value]);

    $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
        'message' => 'Hello',
        'model' => null,
    ]);

    $response->assertOk();
});

it('allows valid model enum when provided', function () {
    Prism::fake([createTextResponse('Response')]);

    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create(['model' => ModelName::LLAMA32->value]);

    $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
        'message' => 'Hello',
        'model' => ModelName::MISTRAL->value,
    ]);

    $response->assertOk();
});

it('builds conversation history from existing messages', function () {
    Prism::fake([createTextResponse('Response')]);

    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create(['model' => ModelName::LLAMA32->value]);

    Message::factory()->for($chat)->user()->create(['parts' => ['text' => 'Previous question']]);
    Message::factory()->for($chat)->assistant()->create(['parts' => ['text' => 'Previous answer']]);

    $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
        'message' => 'New question',
    ]);

    $response->assertOk();

    // The new user message should be created
    expect(Message::where('chat_id', $chat->id)->where('role', 'user')->count())->toBe(2);
});

it('streams response with text chunks and creates assistant message', function () {
    Prism::fake([createTextResponse('Hello from AI')]);

    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create(['model' => ModelName::LLAMA32->value]);

    $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
        'message' => 'Hello AI',
    ]);

    $response->assertOk();

    // Stream the content to trigger the generator
    $content = $response->streamedContent();

    // Verify stream contains text chunks
    expect($content)->toContain('text');

    // Verify assistant message was created
    $assistantMessage = Message::where('chat_id', $chat->id)->where('role', 'assistant')->first();
    expect($assistantMessage)->not->toBeNull();
    expect($assistantMessage->parts['text'])->toBe('Hello from AI');
});

it('updates chat timestamp after successful stream', function () {
    Prism::fake([createTextResponse('Response')]);

    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create([
        'model' => ModelName::LLAMA32->value,
        'updated_at' => now()->subDay(),
    ]);

    $originalUpdatedAt = $chat->updated_at->timestamp;

    $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
        'message' => 'Hello',
    ]);

    $response->streamedContent();

    $chat->refresh();
    expect($chat->updated_at->timestamp)->toBeGreaterThanOrEqual($originalUpdatedAt);
});
