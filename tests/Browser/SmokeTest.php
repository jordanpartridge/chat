<?php

declare(strict_types=1);

use App\Models\Chat;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('smoke tests all public pages', function (): void {
    $pages = visit(['/login', '/register']);

    $pages->assertNoSmoke();
});

it('smoke tests authenticated routes', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $chat = Chat::factory()->for($user)->create();

    $this->actingAs($user);

    $pages = visit([
        '/dashboard',
        '/chats',
        '/chats/'.$chat->id,
        '/settings/profile',
        '/settings/password',
        '/settings/appearance',
    ]);

    $pages->assertNoSmoke();
});

it('verifies dark mode works on chat page', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $chat = Chat::factory()->for($user)->create();

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id)->inDarkMode();

    $page->assertNoJavaScriptErrors()
        ->screenshot(filename: 'dark-mode-chat');
});

it('verifies mobile viewport works', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
    $chat = Chat::factory()->for($user)->create();

    $this->actingAs($user);

    $page = visit('/chats/'.$chat->id)->on()->mobile();

    $page->assertNoJavaScriptErrors()
        ->screenshot(filename: 'mobile-chat');
});
