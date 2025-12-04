<?php

declare(strict_types=1);

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('can view the login page', function (): void {
    $page = visit('/login');

    $page->assertSee('Log in')
        ->assertNoJavaScriptErrors();
});

it('can log in with valid credentials', function (): void {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);

    $page = visit('/login');

    $page->fill('email', 'test@example.com')
        ->fill('password', 'password')
        ->click('Log in')
        ->assertUrlIs(url('/dashboard'))
        ->assertSee('Dashboard')
        ->assertNoJavaScriptErrors();

    $this->assertAuthenticated();
});

it('cannot log in with invalid credentials', function (): void {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $page = visit('/login');

    $page->fill('email', 'test@example.com')
        ->fill('password', 'wrong-password')
        ->click('Log in')
        ->assertUrlIs(url('/login'))
        ->assertSee('These credentials do not match our records')
        ->assertNoJavaScriptErrors();
});

it('can view the registration page', function (): void {
    $page = visit('/register');

    $page->assertSee('Create an account')
        ->assertNoJavaScriptErrors();
});

it('can register a new user', function (): void {
    $page = visit('/register');

    $page->fill('name', 'Test User')
        ->fill('email', 'newuser@example.com')
        ->fill('password', 'password123')
        ->fill('password_confirmation', 'password123')
        ->click('Create account')
        ->assertUrlIs(url('/dashboard'))
        ->assertNoJavaScriptErrors();

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
});
