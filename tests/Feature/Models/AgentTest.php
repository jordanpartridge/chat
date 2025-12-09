<?php

use App\Models\Agent;
use App\Models\AiModel;
use App\Models\Chat;
use App\Models\User;

describe('agent model', function () {
    it('can be created with a factory', function () {
        $agent = Agent::factory()->create();
        expect($agent)->toBeInstanceOf(Agent::class);
    });

    it('belongs to a user', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->for($user)->create();

        expect($agent->user)->toBeInstanceOf(User::class)
            ->and($agent->user->id)->toBe($user->id);
    });

    it('belongs to a default model', function () {
        $aiModel = AiModel::factory()->create();
        $agent = Agent::factory()->create(['default_model_id' => $aiModel->id]);

        expect($agent->defaultModel)->toBeInstanceOf(AiModel::class)
            ->and($agent->defaultModel->id)->toBe($aiModel->id);
    });

    it('has many chats', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->for($user)->create();
        $chat = Chat::factory()->for($user)->create(['agent_id' => $agent->id]);

        expect($agent->chats)->toHaveCount(1)
            ->and($agent->chats->first()->id)->toBe($chat->id);
    });

    it('casts tools to array', function () {
        $agent = Agent::factory()->create(['tools' => ['search', 'create']]);

        expect($agent->tools)->toBeArray()
            ->and($agent->tools)->toBe(['search', 'create']);
    });

    it('casts capabilities to array', function () {
        $agent = Agent::factory()->create(['capabilities' => ['code', 'analysis']]);

        expect($agent->capabilities)->toBeArray()
            ->and($agent->capabilities)->toBe(['code', 'analysis']);
    });

    it('casts is_active to boolean', function () {
        $agent = Agent::factory()->create(['is_active' => 1]);

        expect($agent->is_active)->toBeBool()
            ->and($agent->is_active)->toBeTrue();
    });
});
