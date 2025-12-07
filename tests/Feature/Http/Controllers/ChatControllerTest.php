<?php

use App\Models\AiModel;
use App\Models\Chat;
use App\Models\User;

describe('index', function () {
    it('redirects guests to login', function () {
        $response = $this->get(route('chats.index'));

        $response->assertRedirect(route('login'));
    });

    it('displays chats for authenticated users', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('chats.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Chat/Index')
            ->has('chats')
            ->has('models')
        );
    });

    it('returns models with correct properties', function () {
        $user = User::factory()->create();
        $credential = \App\Models\UserApiCredential::factory()->for($user)->create();
        $model = AiModel::factory()->for($credential, 'credential')->create([
            'name' => 'Test Model',
            'model_id' => 'test-model-id',
            'supports_tools' => true,
            'supports_vision' => false,
        ]);

        $response = $this->actingAs($user)->get(route('chats.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Chat/Index')
            ->has('models', 1)
            ->where('models.0.id', $model->id)
            ->where('models.0.name', 'Test Model')
            ->where('models.0.model_id', 'test-model-id')
            ->where('models.0.supports_tools', true)
            ->where('models.0.supports_vision', false)
        );
    });

    it('orders chats by updated_at descending', function () {
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
});

describe('store', function () {
    it('creates a chat and redirects to show', function () {
        $user = User::factory()->create();
        $aiModel = AiModel::factory()->create();

        $response = $this->actingAs($user)->post(route('chats.store'), [
            'message' => 'Hello, this is my first message',
            'ai_model_id' => $aiModel->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('chats', [
            'user_id' => $user->id,
            'ai_model_id' => $aiModel->id,
        ]);
    });

    it('truncates title to 50 characters plus ellipsis', function () {
        $user = User::factory()->create();
        $aiModel = AiModel::factory()->create();
        $longMessage = str_repeat('a', 100);

        $this->actingAs($user)->post(route('chats.store'), [
            'message' => $longMessage,
            'ai_model_id' => $aiModel->id,
        ]);

        $chat = Chat::where('user_id', $user->id)->first();
        expect($chat->title)->toEndWith('...');
        expect(strlen($chat->title))->toBeLessThanOrEqual(53);
    });

    it('requires message field', function () {
        $user = User::factory()->create();
        $aiModel = AiModel::factory()->create();

        $response = $this->actingAs($user)->post(route('chats.store'), [
            'ai_model_id' => $aiModel->id,
        ]);

        $response->assertSessionHasErrors('message');
    });

    it('requires ai_model_id field', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('chats.store'), [
            'message' => 'Hello',
        ]);

        $response->assertSessionHasErrors('ai_model_id');
    });

    it('requires a valid ai_model_id', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('chats.store'), [
            'message' => 'Hello',
            'ai_model_id' => 99999,
        ]);

        $response->assertSessionHasErrors('ai_model_id');
    });
});

describe('show', function () {
    it('redirects guests to login', function () {
        $chat = Chat::factory()->create();

        $response = $this->get(route('chats.show', $chat));

        $response->assertRedirect(route('login'));
    });

    it('displays the chat for the owner', function () {
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
});

describe('update', function () {
    it('updates the chat ai model', function () {
        $user = User::factory()->create();
        $originalModel = AiModel::factory()->create();
        $newModel = AiModel::factory()->create();
        $chat = Chat::factory()->for($user)->create(['ai_model_id' => $originalModel->id]);

        $response = $this->actingAs($user)->patch(route('chats.update', $chat), [
            'ai_model_id' => $newModel->id,
        ]);

        $response->assertRedirect();
        expect($chat->fresh()->ai_model_id)->toBe($newModel->id);
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

    it('validates title max length', function () {
        $user = User::factory()->create();
        $chat = Chat::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(route('chats.update', $chat), [
            'title' => str_repeat('a', 300),
        ]);

        $response->assertSessionHasErrors('title');
    });
});

describe('destroy', function () {
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
});
