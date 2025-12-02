<?php

use App\Models\Chat;
use App\Models\Message;

it('has a valid factory', function () {
    $message = Message::factory()->create();

    expect($message)->toBeInstanceOf(Message::class);
    expect($message->exists)->toBeTrue();
});

it('uses UUIDs as primary key', function () {
    $message = Message::factory()->create();

    expect($message->id)->toBeString();
    expect(strlen($message->id))->toBe(36); // UUID format
});

it('belongs to a chat', function () {
    $chat = Chat::factory()->create();
    $message = Message::factory()->for($chat)->create();

    expect($message->chat)->toBeInstanceOf(Chat::class);
    expect($message->chat->id)->toBe($chat->id);
});

it('has a role attribute', function () {
    $message = Message::factory()->create(['role' => 'user']);

    expect($message->role)->toBe('user');
});

it('has a parts attribute as array', function () {
    $message = Message::factory()->create([
        'parts' => ['text' => 'Hello, world!'],
    ]);

    expect($message->parts)->toBeArray();
    expect($message->parts['text'])->toBe('Hello, world!');
});

it('casts parts to array automatically', function () {
    $message = Message::factory()->create([
        'parts' => ['text' => 'Test message', 'additional' => 'data'],
    ]);

    // Refresh from database to verify casting works
    $message->refresh();

    expect($message->parts)->toBeArray();
    expect($message->parts['text'])->toBe('Test message');
    expect($message->parts['additional'])->toBe('data');
});

it('can create user messages via factory state', function () {
    $message = Message::factory()->user()->create();

    expect($message->role)->toBe('user');
});

it('can create assistant messages via factory state', function () {
    $message = Message::factory()->assistant()->create();

    expect($message->role)->toBe('assistant');
});

it('has timestamps', function () {
    $message = Message::factory()->create();

    expect($message->created_at)->not->toBeNull();
    expect($message->updated_at)->not->toBeNull();
});

it('has many artifacts', function () {
    $message = Message::factory()->create();
    $artifacts = \App\Models\Artifact::factory()->count(3)->for($message)->create();

    expect($message->artifacts)->toHaveCount(3);
    expect($message->artifacts->first())->toBeInstanceOf(\App\Models\Artifact::class);
});
