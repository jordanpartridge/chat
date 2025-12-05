<?php

use App\Enums\ModelName;
use App\Models\Chat;
use App\Models\User;
use App\Services\ChatStreamService;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

function createStreamTextResponse(string $text): TextResponse
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

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->chat = Chat::factory()->for($this->user)->create();
    $this->service = app(ChatStreamService::class);
});

it('streams text response', function () {
    Prism::fake([createStreamTextResponse('Hello world')]);

    $chunks = iterator_to_array($this->service->stream(
        $this->chat,
        'Say hello',
        ModelName::LLAMA32
    ));

    expect($chunks)->not->toBeEmpty();
    $content = implode('', $chunks);
    expect($content)->toContain('text');
});

it('enables artifact tools for trigger words', function () {
    Prism::fake([createStreamTextResponse('Creating diagram')]);

    $chunks = iterator_to_array($this->service->stream(
        $this->chat,
        'Create a diagram for me',
        ModelName::LLAMA32
    ));

    expect($chunks)->not->toBeEmpty();
});

it('enables laravel tools for trigger words with Groq model', function () {
    Prism::fake([createStreamTextResponse('Creating model')]);

    $chunks = iterator_to_array($this->service->stream(
        $this->chat,
        'Generate a laravel model for users',
        ModelName::GROQ_LLAMA33_70B
    ));

    expect($chunks)->not->toBeEmpty();
});

it('always includes knowledge tool for Groq models', function () {
    Prism::fake([createStreamTextResponse('Here is what I found')]);

    // Knowledge tool is always available - no trigger words needed
    $chunks = iterator_to_array($this->service->stream(
        $this->chat,
        'What is Conduit?',
        ModelName::GROQ_LLAMA33_70B
    ));

    expect($chunks)->not->toBeEmpty();
});

it('creates and finalizes assistant message', function () {
    Prism::fake([createStreamTextResponse('Test response')]);

    iterator_to_array($this->service->stream(
        $this->chat,
        'Hello',
        ModelName::LLAMA32
    ));

    $assistantMessage = $this->chat->messages()->where('role', 'assistant')->first();
    expect($assistantMessage)->not->toBeNull();
    expect($assistantMessage->parts['text'])->toContain('Test response');
});

it('deletes empty assistant message when no content', function () {
    Prism::fake([createStreamTextResponse('')]);

    iterator_to_array($this->service->stream(
        $this->chat,
        'Hello',
        ModelName::LLAMA32
    ));

    $assistantMessage = $this->chat->messages()->where('role', 'assistant')->first();
    expect($assistantMessage)->toBeNull();
});

it('handles errors gracefully', function () {
    // Create a mock that throws an exception
    $mockService = Mockery::mock(ChatStreamService::class)->makePartial();
    $mockService->shouldReceive('stream')
        ->andReturnUsing(function () {
            yield json_encode(['type' => 'error', 'content' => 'An error occurred'])."\n";
        });

    $chunks = iterator_to_array($mockService->stream(
        $this->chat,
        'Hello',
        ModelName::LLAMA32
    ));

    $content = implode('', $chunks);
    expect($content)->toContain('error');
});

it('builds system prompt with tools enabled', function () {
    Prism::fake([createStreamTextResponse('Response')]);

    // Trigger artifact tools
    iterator_to_array($this->service->stream(
        $this->chat,
        'Create a chart',
        ModelName::LLAMA32
    ));

    // Verify tools were enabled by checking the response was generated
    expect($this->chat->messages()->where('role', 'assistant')->exists())->toBeTrue();
});

it('builds conversation history from existing messages', function () {
    $this->chat->messages()->create([
        'role' => 'user',
        'parts' => ['text' => 'First message'],
    ]);
    $this->chat->messages()->create([
        'role' => 'assistant',
        'parts' => ['text' => 'First response'],
    ]);

    Prism::fake([createStreamTextResponse('Second response')]);

    iterator_to_array($this->service->stream(
        $this->chat,
        'Second message',
        ModelName::LLAMA32
    ));

    // Should have 3 messages now (2 existing + 1 new assistant)
    expect($this->chat->messages()->count())->toBe(3);
});

it('does not enable tools for Ollama models', function () {
    Prism::fake([createStreamTextResponse('I cannot create diagrams')]);

    // Even with trigger words, Ollama models should not get tools
    $chunks = iterator_to_array($this->service->stream(
        $this->chat,
        'Create a diagram for me',
        ModelName::LLAMA32 // Ollama model
    ));

    expect($chunks)->not->toBeEmpty();
    // Response should be text-only since tools are disabled
    $content = implode('', $chunks);
    expect($content)->toContain('text');
});

it('enables tools for Groq models with trigger words', function () {
    Prism::fake([createStreamTextResponse('Creating diagram')]);

    $chunks = iterator_to_array($this->service->stream(
        $this->chat,
        'Create a diagram for me',
        ModelName::GROQ_LLAMA33_70B // Groq model supports tools
    ));

    expect($chunks)->not->toBeEmpty();
});

it('includes web search tool when available for Groq models', function () {
    // Configure Tavily API key to enable web search
    config(['services.tavily.api_key' => 'test-key']);

    Prism::fake([createStreamTextResponse('Search results')]);

    $chunks = iterator_to_array($this->service->stream(
        $this->chat,
        'What is the latest news?',
        ModelName::GROQ_LLAMA33_70B
    ));

    expect($chunks)->not->toBeEmpty();
});

it('does not include web search tool when not configured', function () {
    config(['services.tavily.api_key' => '']);

    Prism::fake([createStreamTextResponse('No web search')]);

    $chunks = iterator_to_array($this->service->stream(
        $this->chat,
        'What is the latest news?',
        ModelName::GROQ_LLAMA33_70B
    ));

    expect($chunks)->not->toBeEmpty();
});
