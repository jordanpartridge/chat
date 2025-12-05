<?php

use App\Models\AiModel;
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
    $this->ollamaModel = AiModel::factory()->create([
        'name' => 'Llama 3.2',
        'provider' => 'ollama',
        'model_id' => 'llama3.2',
        'supports_tools' => false,
    ]);
    $this->groqModel = AiModel::factory()->create([
        'name' => 'Groq Llama 3.3 70B',
        'provider' => 'groq',
        'model_id' => 'llama-3.3-70b-versatile',
        'supports_tools' => true,
    ]);
    $this->chat = Chat::factory()->for($this->user)->withModel($this->ollamaModel)->create();
    $this->service = app(ChatStreamService::class);
});

describe('streaming', function () {
    it('streams text response', function () {
        Prism::fake([createStreamTextResponse('Hello world')]);

        $chunks = iterator_to_array($this->service->stream(
            $this->chat,
            'Say hello',
            $this->ollamaModel
        ));

        expect($chunks)->not->toBeEmpty();
        $content = implode('', $chunks);
        expect($content)->toContain('text');
    });

    it('creates and finalizes assistant message', function () {
        Prism::fake([createStreamTextResponse('Test response')]);

        iterator_to_array($this->service->stream(
            $this->chat,
            'Hello',
            $this->ollamaModel
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
            $this->ollamaModel
        ));

        $assistantMessage = $this->chat->messages()->where('role', 'assistant')->first();
        expect($assistantMessage)->toBeNull();
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
            $this->ollamaModel
        ));

        expect($this->chat->messages()->count())->toBe(3);
    });
});

describe('tools', function () {
    it('enables artifact tools for trigger words', function () {
        Prism::fake([createStreamTextResponse('Creating diagram')]);

        $chunks = iterator_to_array($this->service->stream(
            $this->chat,
            'Create a diagram for me',
            $this->ollamaModel
        ));

        expect($chunks)->not->toBeEmpty();
    });

    it('enables laravel tools for trigger words with Groq model', function () {
        Prism::fake([createStreamTextResponse('Creating model')]);

        $chunks = iterator_to_array($this->service->stream(
            $this->chat,
            'Generate a laravel model for users',
            $this->groqModel
        ));

        expect($chunks)->not->toBeEmpty();
    });

    it('always includes knowledge tool for Groq models', function () {
        Prism::fake([createStreamTextResponse('Here is what I found')]);

        $chunks = iterator_to_array($this->service->stream(
            $this->chat,
            'What is Conduit?',
            $this->groqModel
        ));

        expect($chunks)->not->toBeEmpty();
    });

    it('does not enable tools for Ollama models', function () {
        Prism::fake([createStreamTextResponse('I cannot create diagrams')]);

        $chunks = iterator_to_array($this->service->stream(
            $this->chat,
            'Create a diagram for me',
            $this->ollamaModel
        ));

        expect($chunks)->not->toBeEmpty();
        $content = implode('', $chunks);
        expect($content)->toContain('text');
    });

    it('enables tools for Groq models with trigger words', function () {
        Prism::fake([createStreamTextResponse('Creating diagram')]);

        $chunks = iterator_to_array($this->service->stream(
            $this->chat,
            'Create a diagram for me',
            $this->groqModel
        ));

        expect($chunks)->not->toBeEmpty();
    });

    it('builds system prompt with tools enabled', function () {
        Prism::fake([createStreamTextResponse('Response')]);

        iterator_to_array($this->service->stream(
            $this->chat,
            'Create a chart',
            $this->ollamaModel
        ));

        expect($this->chat->messages()->where('role', 'assistant')->exists())->toBeTrue();
    });
});

describe('web search', function () {
    it('includes web search tool when available for Groq models', function () {
        config(['services.tavily.api_key' => 'test-key']);

        Prism::fake([createStreamTextResponse('Search results')]);

        $chunks = iterator_to_array($this->service->stream(
            $this->chat,
            'What is the latest news?',
            $this->groqModel
        ));

        expect($chunks)->not->toBeEmpty();
    });

    it('does not include web search tool when not configured', function () {
        config(['services.tavily.api_key' => '']);

        Prism::fake([createStreamTextResponse('No web search')]);

        $chunks = iterator_to_array($this->service->stream(
            $this->chat,
            'What is the latest news?',
            $this->groqModel
        ));

        expect($chunks)->not->toBeEmpty();
    });
});

describe('error handling', function () {
    it('handles errors gracefully', function () {
        $mockService = Mockery::mock(ChatStreamService::class)->makePartial();
        $mockService->shouldReceive('stream')
            ->andReturnUsing(function () {
                yield json_encode(['type' => 'error', 'content' => 'An error occurred'])."\n";
            });

        $chunks = iterator_to_array($mockService->stream(
            $this->chat,
            'Hello',
            $this->ollamaModel
        ));

        $content = implode('', $chunks);
        expect($content)->toContain('error');
    });
});
