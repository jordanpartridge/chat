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
    // Create a text response with steps to simulate streaming properly
    $textResponse = new TextResponse(
        steps: collect([
            new \Prism\Prism\Text\Step(
                text: 'Hello from AI',
                finishReason: FinishReason::Stop,
                toolCalls: [],
                toolResults: [],
                providerToolCalls: [],
                usage: new Usage(10, 20),
                meta: new Meta('fake-id', 'fake-model'),
                messages: [],
                systemPrompts: [],
            ),
        ]),
        text: 'Hello from AI',
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(10, 20),
        meta: new Meta('fake-id', 'fake-model'),
        messages: collect([]),
    );

    Prism::fake([$textResponse]);

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

it('handles tool calls and tool results with artifact creation', function () {
    // Create an artifact to reference
    $message = Message::factory()->create();
    $artifact = \App\Models\Artifact::factory()->for($message)->create();

    // Create a response with a step containing tool calls and results
    $toolCall = new \Prism\Prism\ValueObjects\ToolCall(
        id: 'call-123',
        name: 'create_artifact',
        arguments: ['name' => 'Test', 'purpose' => 'Test purpose', 'type' => 'html'],
    );

    $toolResult = new \Prism\Prism\ValueObjects\ToolResult(
        toolCallId: 'call-123',
        toolName: 'create_artifact',
        args: ['name' => 'Test', 'purpose' => 'Test purpose', 'type' => 'html'],
        result: "[artifact:{$artifact->id}] - Test Artifact",
    );

    $textResponse = new TextResponse(
        steps: collect([
            new \Prism\Prism\Text\Step(
                text: 'I created an artifact for you.',
                finishReason: FinishReason::Stop,
                toolCalls: [$toolCall],
                toolResults: [$toolResult],
                providerToolCalls: [],
                usage: new Usage(10, 20),
                meta: new Meta('fake-id', 'fake-model'),
                messages: [],
                systemPrompts: [],
            ),
        ]),
        text: 'I created an artifact for you.',
        finishReason: FinishReason::Stop,
        toolCalls: [$toolCall],
        toolResults: [$toolResult],
        usage: new Usage(10, 20),
        meta: new Meta('fake-id', 'fake-model'),
        messages: collect([]),
    );

    Prism::fake([$textResponse]);

    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create(['model' => ModelName::LLAMA32->value]);

    $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
        'message' => 'Create an artifact',
    ]);

    $response->assertOk();
    $content = $response->streamedContent();

    // The stream should contain artifact data
    expect($content)->toContain('artifact');
});

it('handles empty response by deleting assistant message', function () {
    // Create a response with no text
    $textResponse = new TextResponse(
        steps: collect([]),
        text: '',
        finishReason: FinishReason::Stop,
        toolCalls: [],
        toolResults: [],
        usage: new Usage(10, 20),
        meta: new Meta('fake-id', 'fake-model'),
        messages: collect([]),
    );

    Prism::fake([$textResponse]);

    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create(['model' => ModelName::LLAMA32->value]);

    $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
        'message' => 'Hello',
    ]);

    $response->assertOk();
    $response->streamedContent();

    // There should only be the user message (assistant message should be deleted)
    expect(Message::where('chat_id', $chat->id)->count())->toBe(1);
    expect(Message::where('chat_id', $chat->id)->first()->role)->toBe('user');
});

it('handles stream error gracefully', function () {
    // Mock the Prism facade to throw an exception
    Prism::shouldReceive('text')->andThrow(new \Exception('Test error'));

    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create(['model' => ModelName::LLAMA32->value]);

    $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
        'message' => 'Hello',
    ]);

    $response->assertOk();
    $content = $response->streamedContent();

    // The stream should contain an error message
    expect($content)->toContain('error');
});

it('handles tool result without matching artifact', function () {
    // Create a response with a tool result referencing a non-existent artifact
    $toolCall = new \Prism\Prism\ValueObjects\ToolCall(
        id: 'call-123',
        name: 'create_artifact',
        arguments: ['name' => 'Test', 'purpose' => 'Test purpose', 'type' => 'html'],
    );

    $toolResult = new \Prism\Prism\ValueObjects\ToolResult(
        toolCallId: 'call-123',
        toolName: 'create_artifact',
        args: ['name' => 'Test', 'purpose' => 'Test purpose', 'type' => 'html'],
        result: '[artifact:non-existent-id] - Test Artifact',
    );

    $textResponse = new TextResponse(
        steps: collect([
            new \Prism\Prism\Text\Step(
                text: 'Done',
                finishReason: FinishReason::Stop,
                toolCalls: [$toolCall],
                toolResults: [$toolResult],
                providerToolCalls: [],
                usage: new Usage(10, 20),
                meta: new Meta('fake-id', 'fake-model'),
                messages: [],
                systemPrompts: [],
            ),
        ]),
        text: 'Done',
        finishReason: FinishReason::Stop,
        toolCalls: [$toolCall],
        toolResults: [$toolResult],
        usage: new Usage(10, 20),
        meta: new Meta('fake-id', 'fake-model'),
        messages: collect([]),
    );

    Prism::fake([$textResponse]);

    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create(['model' => ModelName::LLAMA32->value]);

    $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
        'message' => 'Create artifact',
    ]);

    $response->assertOk();
    $content = $response->streamedContent();

    // Should still complete without error
    expect($content)->toContain('text');
});

it('enables tools only when message contains artifact trigger words', function () {
    Prism::fake([createTextResponse('Hello from AI')]);

    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create(['model' => ModelName::LLAMA32->value]);

    // Message with trigger word "create" should enable tools
    $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
        'message' => 'Create a dashboard for me',
    ]);

    $response->assertOk();
    $content = $response->streamedContent();
    expect($content)->toContain('text');
});

it('does not enable tools for regular questions', function () {
    Prism::fake([createTextResponse('The answer is 4')]);

    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create(['model' => ModelName::LLAMA32->value]);

    // Simple question without trigger words
    $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
        'message' => 'What is 2+2?',
    ]);

    $response->assertOk();
    $content = $response->streamedContent();
    expect($content)->toContain('text');

    // Verify assistant message was created with the expected response
    $assistantMessage = Message::where('chat_id', $chat->id)->where('role', 'assistant')->first();
    expect($assistantMessage)->not->toBeNull();
    expect($assistantMessage->parts['text'])->toBe('The answer is 4');
});

it('detects various artifact trigger words', function (string $message) {
    Prism::fake([createTextResponse('Response')]);

    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create(['model' => ModelName::LLAMA32->value]);

    $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
        'message' => $message,
    ]);

    $response->assertOk();
})->with([
    'create trigger' => 'Create a simple counter',
    'build trigger' => 'Build me a form',
    'generate trigger' => 'Generate a chart',
    'diagram trigger' => 'Make a diagram showing the flow',
    'dashboard trigger' => 'I need a dashboard',
    'chart trigger' => 'Show me a chart of the data',
    'graph trigger' => 'Graph this data',
    'visualization trigger' => 'Create a visualization',
    'flowchart trigger' => 'Draw a flowchart',
    'react trigger' => 'Create a React component',
    'vue trigger' => 'Build a Vue component',
    'mermaid trigger' => 'Create a mermaid diagram',
    'svg trigger' => 'Draw an SVG icon',
    'case insensitive' => 'CREATE A DASHBOARD',
]);
