<?php

use App\Models\Agent;
use App\Models\AiModel;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;

describe('factory', function () {
    it('creates a valid chat', function () {
        $chat = Chat::factory()->create();

        expect($chat)->toBeInstanceOf(Chat::class);
    });

    it('creates chat with specified title', function () {
        $chat = Chat::factory()->state(['title' => 'test'])->create();

        expect($chat->title)->toEqual('test');
    });
});

describe('relationships', function () {
    it('belongs to a user', function () {
        $user = User::factory()->create();
        $chat = Chat::factory()->for($user)->create();

        expect($chat->user)->toBeInstanceOf(User::class);
        expect($chat->user->id)->toBe($user->id);
    });

    it('belongs to an AI model', function () {
        $aiModel = AiModel::factory()->create();
        $chat = Chat::factory()->create(['ai_model_id' => $aiModel->id]);

        expect($chat->aiModel)->toBeInstanceOf(AiModel::class);
        expect($chat->aiModel->id)->toBe($aiModel->id);
    });

    it('belongs to an agent', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->for($user)->create();
        $chat = Chat::factory()->for($user)->create(['agent_id' => $agent->id]);

        expect($chat->agent)->toBeInstanceOf(Agent::class);
        expect($chat->agent->id)->toBe($agent->id);
    });

    it('allows null agent', function () {
        $chat = Chat::factory()->create(['agent_id' => null]);

        expect($chat->agent)->toBeNull();
    });

    it('has many messages', function () {
        $chat = Chat::factory()->create();
        Message::factory()->for($chat)->count(3)->create();

        expect($chat->messages)->toHaveCount(3);
    });
});
