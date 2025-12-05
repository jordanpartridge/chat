<?php

declare(strict_types=1);

use App\Http\Requests\UpdateChatRequest;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

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

it('validates model as optional string', function () {
    $request = new UpdateChatRequest;
    $validator = Validator::make(['model' => 123], $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('model'))->toBeTrue();
});

it('validates title max length', function () {
    $request = new UpdateChatRequest;
    $validator = Validator::make(['title' => str_repeat('a', 300)], $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('title'))->toBeTrue();
});

it('passes with valid update data', function () {
    $request = new UpdateChatRequest;
    $validator = Validator::make([
        'model' => 'llama3.2',
        'title' => 'Updated Title',
    ], $request->rules());

    expect($validator->fails())->toBeFalse();
});

it('passes with empty data since fields are optional', function () {
    $request = new UpdateChatRequest;
    $validator = Validator::make([], $request->rules());

    expect($validator->fails())->toBeFalse();
});
