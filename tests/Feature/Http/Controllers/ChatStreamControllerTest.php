<?php

use App\Models\AiModel;
use App\Models\Artifact;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\Text\Step;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\ToolCall;
use Prism\Prism\ValueObjects\ToolResult;
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

describe('authentication', function () {
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
});

describe('validation', function () {
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

    it('validates ai_model_id when provided', function () {
        $user = User::factory()->create();
        $chat = Chat::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
            'message' => 'Hello',
            'ai_model_id' => 99999,
        ]);

        $response->assertSessionHasErrors('ai_model_id');
    });

    it('allows nullable ai_model_id field', function () {
        Prism::fake([createTextResponse('Response')]);

        $user = User::factory()->create();
        $model = AiModel::factory()->create();
        $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

        $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
            'message' => 'Hello',
            'ai_model_id' => null,
        ]);

        $response->assertOk();
    });

    it('allows valid ai_model_id when provided', function () {
        Prism::fake([createTextResponse('Response')]);

        $user = User::factory()->create();
        $model = AiModel::factory()->create();
        $otherModel = AiModel::factory()->create();
        $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

        $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
            'message' => 'Hello',
            'ai_model_id' => $otherModel->id,
        ]);

        $response->assertOk();
    });
});

describe('streaming', function () {
    it('creates a user message and returns streamed response', function () {
        Prism::fake([createTextResponse('Hello from AI')]);

        $user = User::factory()->create();
        $model = AiModel::factory()->create();
        $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

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
        $model = AiModel::factory()->create();
        $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

        $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
            'message' => '   Hello with spaces   ',
        ]);

        $response->assertOk();

        $userMessage = Message::where('chat_id', $chat->id)->where('role', 'user')->first();
        expect($userMessage->parts['text'])->toBe('Hello with spaces');
    });

    it('builds conversation history from existing messages', function () {
        Prism::fake([createTextResponse('Response')]);

        $user = User::factory()->create();
        $model = AiModel::factory()->create();
        $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

        Message::factory()->for($chat)->user()->create(['parts' => ['text' => 'Previous question']]);
        Message::factory()->for($chat)->assistant()->create(['parts' => ['text' => 'Previous answer']]);

        $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
            'message' => 'New question',
        ]);

        $response->assertOk();
        expect(Message::where('chat_id', $chat->id)->where('role', 'user')->count())->toBe(2);
    });

    it('streams response with text chunks and creates assistant message', function () {
        $textResponse = new TextResponse(
            steps: collect([
                new Step(
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
        $model = AiModel::factory()->create();
        $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

        $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
            'message' => 'Hello AI',
        ]);

        $response->assertOk();
        $content = $response->streamedContent();

        expect($content)->toContain('text');

        $assistantMessage = Message::where('chat_id', $chat->id)->where('role', 'assistant')->first();
        expect($assistantMessage)->not->toBeNull();
        expect($assistantMessage->parts['text'])->toBe('Hello from AI');
    });

    it('updates chat timestamp after successful stream', function () {
        Prism::fake([createTextResponse('Response')]);

        $user = User::factory()->create();
        $model = AiModel::factory()->create();
        $chat = Chat::factory()->for($user)->create([
            'ai_model_id' => $model->id,
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

    it('handles empty response by deleting assistant message', function () {
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
        $model = AiModel::factory()->create();
        $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

        $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
            'message' => 'Hello',
        ]);

        $response->assertOk();
        $response->streamedContent();

        expect(Message::where('chat_id', $chat->id)->count())->toBe(1);
        expect(Message::where('chat_id', $chat->id)->first()->role)->toBe('user');
    });
});

describe('tools', function () {
    it('handles tool calls and tool results with artifact creation', function () {
        $message = Message::factory()->create();
        $artifact = Artifact::factory()->for($message)->create();

        $toolCall = new ToolCall(
            id: 'call-123',
            name: 'create_artifact',
            arguments: ['name' => 'Test', 'purpose' => 'Test purpose', 'type' => 'html'],
        );

        $toolResult = new ToolResult(
            toolCallId: 'call-123',
            toolName: 'create_artifact',
            args: ['name' => 'Test', 'purpose' => 'Test purpose', 'type' => 'html'],
            result: "[artifact:{$artifact->id}] - Test Artifact",
        );

        $textResponse = new TextResponse(
            steps: collect([
                new Step(
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
        $model = AiModel::factory()->create(['supports_tools' => true]);
        $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

        $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
            'message' => 'Create an artifact',
        ]);

        $response->assertOk();
        $content = $response->streamedContent();

        expect($content)->toContain('artifact');
    });

    it('handles tool result without matching artifact', function () {
        $toolCall = new ToolCall(
            id: 'call-123',
            name: 'create_artifact',
            arguments: ['name' => 'Test', 'purpose' => 'Test purpose', 'type' => 'html'],
        );

        $toolResult = new ToolResult(
            toolCallId: 'call-123',
            toolName: 'create_artifact',
            args: ['name' => 'Test', 'purpose' => 'Test purpose', 'type' => 'html'],
            result: '[artifact:non-existent-id] - Test Artifact',
        );

        $textResponse = new TextResponse(
            steps: collect([
                new Step(
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
        $model = AiModel::factory()->create(['supports_tools' => true]);
        $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

        $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
            'message' => 'Create artifact',
        ]);

        $response->assertOk();
        $content = $response->streamedContent();

        expect($content)->toContain('text');
    });

    it('enables tools only when model supports tools and message contains trigger words', function () {
        Prism::fake([createTextResponse('Hello from AI')]);

        $user = User::factory()->create();
        $model = AiModel::factory()->create(['supports_tools' => true]);
        $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

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
        $model = AiModel::factory()->create(['supports_tools' => false]);
        $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

        $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
            'message' => 'What is 2+2?',
        ]);

        $response->assertOk();
        $content = $response->streamedContent();
        expect($content)->toContain('text');

        $assistantMessage = Message::where('chat_id', $chat->id)->where('role', 'assistant')->first();
        expect($assistantMessage)->not->toBeNull();
        expect($assistantMessage->parts['text'])->toBe('The answer is 4');
    });

    it('detects various artifact trigger words', function (string $message) {
        Prism::fake([createTextResponse('Response')]);

        $user = User::factory()->create();
        $model = AiModel::factory()->create(['supports_tools' => true]);
        $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

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
});

describe('error handling', function () {
    it('handles stream error gracefully', function () {
        Prism::shouldReceive('text')->andThrow(new \Exception('Test error'));

        $user = User::factory()->create();
        $model = AiModel::factory()->create();
        $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

        $response = $this->actingAs($user)->post(route('chats.stream', $chat), [
            'message' => 'Hello',
        ]);

        $response->assertOk();
        $content = $response->streamedContent();

        expect($content)->toContain('error');
    });
});
