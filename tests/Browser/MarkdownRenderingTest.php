<?php

declare(strict_types=1);

use App\Models\AiModel;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('renders markdown in assistant messages', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $model = AiModel::factory()->create(['is_available' => true]);
    $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

    // Create an assistant message with markdown content
    Message::factory()->for($chat)->assistant()->create([
        'parts' => ['text' => "# Hello World\n\nThis is **bold** and *italic* text.\n\n- Item 1\n- Item 2\n\n```php\necho 'code block';\n```"],
    ]);

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    // Verify markdown is rendered as HTML (h1 should become an actual h1 element)
    $page->assertNoJavaScriptErrors()
        ->assertScript('document.querySelector("[data-testid=markdown-content] h1")?.textContent', 'Hello World')
        ->assertScript('document.querySelector("[data-testid=markdown-content] strong")?.textContent', 'bold')
        ->assertScript('document.querySelector("[data-testid=markdown-content] em")?.textContent', 'italic')
        ->assertScript('document.querySelector("[data-testid=markdown-content] ul") !== null', true)
        ->assertScript('document.querySelector("[data-testid=markdown-content] pre") !== null', true);
});

it('keeps user messages as plain text', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $model = AiModel::factory()->create(['is_available' => true]);
    $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

    // Create a user message with markdown-like content
    Message::factory()->for($chat)->user()->create([
        'parts' => ['text' => '# This should NOT be a heading\n\n**Not bold**'],
    ]);

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    // Verify user message content is NOT rendered as markdown (no h1 element)
    $page->assertNoJavaScriptErrors()
        ->assertScript('document.querySelector("[data-testid=user-message] h1")', null)
        ->assertScript('document.querySelector("[data-testid=plain-text-content]") !== null', true);
});

it('renders code blocks with proper formatting', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $model = AiModel::factory()->create(['is_available' => true]);
    $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

    Message::factory()->for($chat)->assistant()->create([
        'parts' => ['text' => "Here's some code:\n\n```javascript\nconst hello = 'world';\nconsole.log(hello);\n```"],
    ]);

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    // Verify code block is rendered
    $page->assertNoJavaScriptErrors()
        ->assertScript('document.querySelector("[data-testid=markdown-content] pre code") !== null', true);
});

it('renders inline code properly', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $model = AiModel::factory()->create(['is_available' => true]);
    $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

    Message::factory()->for($chat)->assistant()->create([
        'parts' => ['text' => 'Use the `console.log()` function to debug.'],
    ]);

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    // Verify inline code is rendered
    $page->assertNoJavaScriptErrors()
        ->assertScript('document.querySelector("[data-testid=markdown-content] code")?.textContent', 'console.log()');
});

it('renders ordered and unordered lists', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $model = AiModel::factory()->create(['is_available' => true]);
    $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

    Message::factory()->for($chat)->assistant()->create([
        'parts' => ['text' => "Unordered list:\n- Apple\n- Banana\n\nOrdered list:\n1. First\n2. Second"],
    ]);

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    // Verify both list types are rendered
    $page->assertNoJavaScriptErrors()
        ->assertScript('document.querySelector("[data-testid=markdown-content] ul") !== null', true)
        ->assertScript('document.querySelector("[data-testid=markdown-content] ol") !== null', true);
});

it('renders links correctly', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $model = AiModel::factory()->create(['is_available' => true]);
    $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

    Message::factory()->for($chat)->assistant()->create([
        'parts' => ['text' => 'Check out [Laravel](https://laravel.com) for more info.'],
    ]);

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    // Verify link is rendered
    $page->assertNoJavaScriptErrors()
        ->assertScript('document.querySelector("[data-testid=markdown-content] a")?.textContent', 'Laravel')
        ->assertScript('document.querySelector("[data-testid=markdown-content] a")?.href', 'https://laravel.com/');
});
