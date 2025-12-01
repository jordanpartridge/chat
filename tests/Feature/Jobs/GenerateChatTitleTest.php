<?php

use App\Enums\ModelName;
use App\Jobs\GenerateChatTitle;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

function createTitleResponse(string $text): TextResponse
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

it('generates a title from conversation messages', function () {
    Prism::fake([createTitleResponse('Weather Discussion')]);

    $chat = Chat::factory()->create([
        'title' => 'New Chat',
        'model' => ModelName::LLAMA32->value,
    ]);

    Message::factory()->for($chat)->user()->create([
        'parts' => ['text' => 'What is the weather like today?'],
    ]);
    Message::factory()->for($chat)->assistant()->create([
        'parts' => ['text' => 'It looks like a sunny day!'],
    ]);

    $job = new GenerateChatTitle($chat);
    $job->handle();

    $chat->refresh();
    expect($chat->title)->toBe('Weather Discussion');
});

it('does nothing when chat has no messages', function () {
    $chat = Chat::factory()->create([
        'title' => 'Original Title',
        'model' => ModelName::LLAMA32->value,
    ]);

    $job = new GenerateChatTitle($chat);
    $job->handle();

    $chat->refresh();
    expect($chat->title)->toBe('Original Title');
});

it('cleans up markdown artifacts from generated title', function () {
    Prism::fake([createTitleResponse('**Bold Title**')]);

    $chat = Chat::factory()->create([
        'title' => 'New Chat',
        'model' => ModelName::LLAMA32->value,
    ]);

    Message::factory()->for($chat)->user()->create([
        'parts' => ['text' => 'Test message'],
    ]);

    $job = new GenerateChatTitle($chat);
    $job->handle();

    $chat->refresh();
    expect($chat->title)->toBe('Bold Title');
});

it('removes quotes from generated title', function () {
    Prism::fake([createTitleResponse('"Quoted Title"')]);

    $chat = Chat::factory()->create([
        'title' => 'New Chat',
        'model' => ModelName::LLAMA32->value,
    ]);

    Message::factory()->for($chat)->user()->create([
        'parts' => ['text' => 'Test message'],
    ]);

    $job = new GenerateChatTitle($chat);
    $job->handle();

    $chat->refresh();
    expect($chat->title)->toBe('Quoted Title');
});

it('does not update title when generated title is empty', function () {
    Prism::fake([createTitleResponse('   ')]);

    $chat = Chat::factory()->create([
        'title' => 'Original Title',
        'model' => ModelName::LLAMA32->value,
    ]);

    Message::factory()->for($chat)->user()->create([
        'parts' => ['text' => 'Test message'],
    ]);

    $job = new GenerateChatTitle($chat);
    $job->handle();

    $chat->refresh();
    expect($chat->title)->toBe('Original Title');
});

it('does not update title when generated title exceeds 100 characters', function () {
    $longTitle = str_repeat('a', 101);
    Prism::fake([createTitleResponse($longTitle)]);

    $chat = Chat::factory()->create([
        'title' => 'Original Title',
        'model' => ModelName::LLAMA32->value,
    ]);

    Message::factory()->for($chat)->user()->create([
        'parts' => ['text' => 'Test message'],
    ]);

    $job = new GenerateChatTitle($chat);
    $job->handle();

    $chat->refresh();
    expect($chat->title)->toBe('Original Title');
});

it('logs warning when Prism throws exception', function () {
    Log::spy();

    $chat = Chat::factory()->create([
        'title' => 'Original Title',
        'model' => ModelName::LLAMA32->value,
    ]);

    Message::factory()->for($chat)->user()->create([
        'parts' => ['text' => 'Test message'],
    ]);

    // Mock to throw an exception
    $this->mock(\Prism\Prism\PrismManager::class, function ($mock) {
        $pendingRequest = $this->mock(\Prism\Prism\Text\PendingRequest::class);
        $pendingRequest->shouldReceive('using')->andReturnSelf();
        $pendingRequest->shouldReceive('withSystemPrompt')->andReturnSelf();
        $pendingRequest->shouldReceive('withPrompt')->andReturnSelf();
        $pendingRequest->shouldReceive('generate')->andThrow(new \RuntimeException('API error'));

        $mock->shouldReceive('text')->andReturn($pendingRequest);
    });

    $job = new GenerateChatTitle($chat);
    $job->handle();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn ($message) => str_contains($message, 'Failed to generate chat title'));

    $chat->refresh();
    expect($chat->title)->toBe('Original Title');
});

it('uses the chat model for title generation', function () {
    $fake = Prism::fake([createTitleResponse('Test Title')]);

    $chat = Chat::factory()->create([
        'title' => 'New Chat',
        'model' => ModelName::MISTRAL->value,
    ]);

    Message::factory()->for($chat)->user()->create([
        'parts' => ['text' => 'Test message'],
    ]);

    $job = new GenerateChatTitle($chat);
    $job->handle();

    $fake->assertRequest(function ($requests) {
        expect($requests[0]->model())->toBe(ModelName::MISTRAL->value);
    });
});

it('implements ShouldQueue interface', function () {
    $chat = Chat::factory()->create();
    $job = new GenerateChatTitle($chat);

    expect($job)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);
});
