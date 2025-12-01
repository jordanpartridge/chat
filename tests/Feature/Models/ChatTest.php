<?php

use App\Models\Chat;

it('has a valid factory', function () {
    $chat = Chat::factory()->create();
    expect($chat)->toBeInstanceOf(Chat::class);
});

it('has a name', function () {
    $chat = Chat::factory()->state(['title' => 'test'])->create();
    expect($chat->title)->toEqual('test');
});
