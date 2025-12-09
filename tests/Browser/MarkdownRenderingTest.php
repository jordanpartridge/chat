<?php

declare(strict_types=1);

use App\Models\AiModel;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('renders markdown content in assistant messages', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $model = AiModel::factory()->create(['is_available' => true]);
    $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

    Message::factory()
        ->for($chat)
        ->assistant()
        ->withMarkdownContent()
        ->create();

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    $page->assertNoJavaScriptErrors()
        ->assertSee('Heading 1')
        ->assertSee('bold text')
        ->assertSee('Code Example');
});

it('renders code blocks with proper styling', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $model = AiModel::factory()->create(['is_available' => true]);
    $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

    Message::factory()
        ->for($chat)
        ->assistant()
        ->withMarkdownContent()
        ->create();

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    $page->assertNoJavaScriptErrors()
        ->assertScript('document.querySelector(".prose pre") !== null', true)
        ->assertScript('document.querySelector(".prose code") !== null', true);
});

it('renders lists correctly', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $model = AiModel::factory()->create(['is_available' => true]);
    $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

    Message::factory()
        ->for($chat)
        ->assistant()
        ->withMarkdownContent()
        ->create();

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    $page->assertNoJavaScriptErrors()
        ->assertScript('document.querySelector(".prose ul") !== null', true)
        ->assertScript('document.querySelector(".prose ol") !== null', true)
        ->assertSee('First item')
        ->assertSee('Numbered one');
});

it('renders blockquotes with proper styling', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $model = AiModel::factory()->create(['is_available' => true]);
    $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

    Message::factory()
        ->for($chat)
        ->assistant()
        ->withMarkdownContent()
        ->create();

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    $page->assertNoJavaScriptErrors()
        ->assertScript('document.querySelector(".prose blockquote") !== null', true)
        ->assertSee('This is a blockquote');
});

it('renders tables correctly', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $model = AiModel::factory()->create(['is_available' => true]);
    $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

    Message::factory()
        ->for($chat)
        ->assistant()
        ->withMarkdownContent()
        ->create();

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    $page->assertNoJavaScriptErrors()
        ->assertScript('document.querySelector(".prose table") !== null', true)
        ->assertSee('Name')
        ->assertSee('Value')
        ->assertSee('Foo')
        ->assertSee('Bar');
});

it('renders links with proper styling', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $model = AiModel::factory()->create(['is_available' => true]);
    $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

    Message::factory()
        ->for($chat)
        ->assistant()
        ->withMarkdownContent()
        ->create();

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    $page->assertNoJavaScriptErrors()
        ->assertScript('document.querySelector(".prose a") !== null', true)
        ->assertSee('Laravel');
});

it('does not render markdown in user messages', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $model = AiModel::factory()->create(['is_available' => true]);
    $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

    Message::factory()
        ->for($chat)
        ->user()
        ->create([
            'parts' => ['text' => '**This should be plain text**'],
        ]);

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    $page->assertNoJavaScriptErrors()
        ->assertSee('**This should be plain text**');
});
