<?php

use App\Enums\ModelName;
use App\Models\Chat;
use App\Models\User;

it('redirects guests to login when accessing index', function () {
    $response = $this->get(route('chats.index'));

    $response->assertRedirect(route('login'));
});

it('redirects guests to login when accessing show', function () {
    $chat = Chat::factory()->create();

    $response = $this->get(route('chats.show', $chat));

    $response->assertRedirect(route('login'));
});

it('displays the index page for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('chats.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Chat/Index')
        ->has('chats')
        ->has('models')
    );
});

it('orders chats by updated_at descending on index', function () {
    $user = User::factory()->create();
    $oldChat = Chat::factory()->for($user)->create(['updated_at' => now()->subDay()]);
    $newChat = Chat::factory()->for($user)->create(['updated_at' => now()]);

    $response = $this->actingAs($user)->get(route('chats.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Chat/Index')
        ->has('chats', 2)
        ->where('chats.0.id', $newChat->id)
        ->where('chats.1.id', $oldChat->id)
    );
});

it('creates a chat and redirects to show', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('chats.store'), [
        'message' => 'Hello, this is my first message',
        'model' => ModelName::LLAMA32->value,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('chats', [
        'user_id' => $user->id,
        'model' => ModelName::LLAMA32->value,
    ]);
});

it('truncates chat title to 50 characters plus ellipsis', function () {
    $user = User::factory()->create();
    $longMessage = str_repeat('a', 100);

    $this->actingAs($user)->post(route('chats.store'), [
        'message' => $longMessage,
        'model' => ModelName::LLAMA32->value,
    ]);

    $chat = Chat::where('user_id', $user->id)->first();
    expect($chat->title)->toEndWith('...');
    expect(strlen($chat->title))->toBeLessThanOrEqual(53);
});

it('requires message field when storing', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('chats.store'), [
        'model' => ModelName::LLAMA32->value,
    ]);

    $response->assertSessionHasErrors('message');
});

it('requires model field when storing', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('chats.store'), [
        'message' => 'Hello',
    ]);

    $response->assertSessionHasErrors('model');
});

it('requires a valid model name when storing', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('chats.store'), [
        'message' => 'Hello',
        'model' => 'invalid-model',
    ]);

    $response->assertSessionHasErrors('model');
});

it('displays the show page for the chat owner', function () {
    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create();

    $response = $this->actingAs($user)->get(route('chats.show', $chat));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Chat/Show')
        ->has('chat')
        ->has('chats')
        ->has('models')
        ->where('chat.id', $chat->id)
    );
});

it('forbids viewing another user\'s chat', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $chat = Chat::factory()->for($otherUser)->create();

    $response = $this->actingAs($user)->get(route('chats.show', $chat));

    $response->assertForbidden();
});

it('deletes the chat and redirects to index', function () {
    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create();

    $response = $this->actingAs($user)->delete(route('chats.destroy', $chat));

    $response->assertRedirect(route('chats.index'));
    $this->assertDatabaseMissing('chats', ['id' => $chat->id]);
});

it('forbids deleting another user\'s chat', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $chat = Chat::factory()->for($otherUser)->create();

    $response = $this->actingAs($user)->delete(route('chats.destroy', $chat));

    $response->assertForbidden();
    $this->assertDatabaseHas('chats', ['id' => $chat->id]);
});

it('updates the chat model', function () {
    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create(['model' => ModelName::LLAMA32->value]);

    $response = $this->actingAs($user)->patch(route('chats.update', $chat), [
        'model' => ModelName::GROQ_LLAMA33_70B->value,
    ]);

    $response->assertRedirect();
    expect($chat->fresh()->model)->toBe(ModelName::GROQ_LLAMA33_70B->value);
});

it('updates the chat title', function () {
    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create(['title' => 'Original Title']);

    $response = $this->actingAs($user)->patch(route('chats.update', $chat), [
        'title' => 'New Title',
    ]);

    $response->assertRedirect();
    expect($chat->fresh()->title)->toBe('New Title');
});

it('forbids updating another user\'s chat', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $chat = Chat::factory()->for($otherUser)->create();

    $response = $this->actingAs($user)->patch(route('chats.update', $chat), [
        'title' => 'Hacked Title',
    ]);

    $response->assertForbidden();
});

it('validates title max length on update', function () {
    $user = User::factory()->create();
    $chat = Chat::factory()->for($user)->create();

    $response = $this->actingAs($user)->patch(route('chats.update', $chat), [
        'title' => str_repeat('a', 300),
    ]);

    $response->assertSessionHasErrors('title');
});
