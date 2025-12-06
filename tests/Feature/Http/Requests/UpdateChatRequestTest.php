<?php

declare(strict_types=1);

use App\Http\Requests\UpdateChatRequest;
use App\Models\AiModel;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

describe('authorization', function () {
    it('authorizes owner to update their chat', function () {
        $user = User::factory()->create();
        $chat = Chat::factory()->for($user)->create();

        $request = new UpdateChatRequest;
        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(fn () => new class($chat)
        {
            public function __construct(private Chat $chat) {}

            public function parameter($key)
            {
                return $this->chat;
            }
        });

        expect($request->authorize())->toBeTrue();
    });

    it('denies update for other users chats', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherChat = Chat::factory()->for($otherUser)->create();

        $request = new UpdateChatRequest;
        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(fn () => new class($otherChat)
        {
            public function __construct(private Chat $chat) {}

            public function parameter($key)
            {
                return $this->chat;
            }
        });

        expect($request->authorize())->toBeFalse();
    });

    it('denies when chat is not found', function () {
        $user = User::factory()->create();

        $request = new UpdateChatRequest;
        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(fn () => new class
        {
            public function parameter($key)
            {
                return null;
            }
        });

        expect($request->authorize())->toBeFalse();
    });
});

describe('validation', function () {
    it('validates ai_model_id as optional integer', function () {
        $request = new UpdateChatRequest;
        $validator = Validator::make(['ai_model_id' => 'not-an-integer'], $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('ai_model_id'))->toBeTrue();
    });

    it('validates ai_model_id must exist in database', function () {
        $request = new UpdateChatRequest;
        $validator = Validator::make(['ai_model_id' => 99999], $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('ai_model_id'))->toBeTrue();
    });

    it('validates title max length', function () {
        $request = new UpdateChatRequest;
        $validator = Validator::make(['title' => str_repeat('a', 300)], $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('title'))->toBeTrue();
    });

    it('passes with valid update data', function () {
        $model = AiModel::factory()->create();

        $request = new UpdateChatRequest;
        $validator = Validator::make([
            'ai_model_id' => $model->id,
            'title' => 'Updated Title',
        ], $request->rules());

        expect($validator->fails())->toBeFalse();
    });

    it('passes with empty data since fields are optional', function () {
        $request = new UpdateChatRequest;
        $validator = Validator::make([], $request->rules());

        expect($validator->fails())->toBeFalse();
    });
});
