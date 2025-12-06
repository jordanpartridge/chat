<?php

declare(strict_types=1);

use App\Models\AiModel;
use App\Models\Chat;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('can view a chat page', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $model = AiModel::factory()->create(['is_available' => true]);
    $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    $page->assertSee($chat->title)
        ->assertNoJavaScriptErrors();
});

it('chat page has proper CSRF meta tag', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $model = AiModel::factory()->create(['is_available' => true]);
    $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    // Verify CSRF token exists in meta tag
    $page->assertScript('document.querySelector(\'meta[name="csrf-token"]\') !== null', true)
        ->assertNoJavaScriptErrors();
});

it('can access the chat input form', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $model = AiModel::factory()->create(['is_available' => true]);
    $chat = Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id);

    // Verify the chat input is visible
    $page->assertVisible('@message-input')
        ->assertNoJavaScriptErrors();
});
