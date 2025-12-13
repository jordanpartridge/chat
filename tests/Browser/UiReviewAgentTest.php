<?php

declare(strict_types=1);

/**
 * UI Review Agent
 *
 * Comprehensive browser test suite that acts as an automated UI reviewer.
 * Systematically checks all pages for visual consistency, JS errors,
 * responsive design, and dark mode support.
 *
 * Run with: php artisan test tests/Browser/UiReviewAgentTest.php
 */

use App\Models\AiModel;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use App\Models\UserApiCredential;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Test Data Setup
|--------------------------------------------------------------------------
*/

function createTestUser(): User
{
    return User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);
}

function createTestChat(User $user): Chat
{
    $model = AiModel::factory()->create(['is_available' => true]);

    return Chat::factory()->for($user)->create(['ai_model_id' => $model->id]);
}

/*
|--------------------------------------------------------------------------
| Public Pages Review
|--------------------------------------------------------------------------
*/

describe('public pages', function () {
    it('reviews welcome page', function () {
        $page = visit('/');

        $page->assertSuccessful()
            ->assertNoJavaScriptErrors()
            ->assertNoConsoleLogs()
            ->screenshot('review/public/welcome-light');

        $page->inDarkMode()
            ->screenshot('review/public/welcome-dark');

        $page->on()->mobile()
            ->screenshot('review/public/welcome-mobile');
    });

    it('reviews login page', function () {
        $page = visit('/login');

        $page->assertSuccessful()
            ->assertSee('Log in')
            ->assertNoJavaScriptErrors()
            ->screenshot('review/public/login-light');

        $page->inDarkMode()
            ->screenshot('review/public/login-dark');

        $page->on()->mobile()
            ->screenshot('review/public/login-mobile');
    });

    it('reviews registration page', function () {
        $page = visit('/register');

        $page->assertSuccessful()
            ->assertSee('Create an account')
            ->assertNoJavaScriptErrors()
            ->screenshot('review/public/register-light');

        $page->inDarkMode()
            ->screenshot('review/public/register-dark');

        $page->on()->mobile()
            ->screenshot('review/public/register-mobile');
    });
});

/*
|--------------------------------------------------------------------------
| Dashboard Review
|--------------------------------------------------------------------------
*/

describe('dashboard', function () {
    it('reviews dashboard page', function () {
        $user = createTestUser();
        $this->actingAs($user);

        $page = visit('/dashboard');

        $page->assertSuccessful()
            ->assertSee('Dashboard')
            ->assertNoJavaScriptErrors()
            ->screenshot('review/dashboard/main-light');

        $page->inDarkMode()
            ->screenshot('review/dashboard/main-dark');

        $page->on()->mobile()
            ->screenshot('review/dashboard/main-mobile');
    });

    it('reviews dashboard with recent chats', function () {
        $user = createTestUser();
        $model = AiModel::factory()->create(['is_available' => true]);

        // Create some chats
        Chat::factory()->count(3)->for($user)->create(['ai_model_id' => $model->id]);

        $this->actingAs($user);

        $page = visit('/dashboard');

        $page->assertSuccessful()
            ->assertNoJavaScriptErrors()
            ->screenshot('review/dashboard/with-chats');
    });
});

/*
|--------------------------------------------------------------------------
| Chat Pages Review
|--------------------------------------------------------------------------
*/

describe('chat pages', function () {
    it('reviews chat index page', function () {
        $user = createTestUser();
        $this->actingAs($user);

        $page = visit('/chats');

        $page->assertSuccessful()
            ->assertNoJavaScriptErrors()
            ->screenshot('review/chats/index-empty-light');

        $page->inDarkMode()
            ->screenshot('review/chats/index-empty-dark');
    });

    it('reviews chat index with chats', function () {
        $user = createTestUser();
        $model = AiModel::factory()->create(['is_available' => true]);
        Chat::factory()->count(5)->for($user)->create(['ai_model_id' => $model->id]);

        $this->actingAs($user);

        $page = visit('/chats');

        $page->assertSuccessful()
            ->assertNoJavaScriptErrors()
            ->screenshot('review/chats/index-with-chats');
    });

    it('reviews individual chat page', function () {
        $user = createTestUser();
        $chat = createTestChat($user);

        $this->actingAs($user);

        $page = visit('/chats/'.$chat->id);

        $page->assertSuccessful()
            ->assertSee($chat->title)
            ->assertNoJavaScriptErrors()
            ->assertVisible('@message-input')
            ->screenshot('review/chats/show-empty-light');

        $page->inDarkMode()
            ->screenshot('review/chats/show-empty-dark');

        $page->on()->mobile()
            ->screenshot('review/chats/show-empty-mobile');
    });

    it('reviews chat page with messages', function () {
        $user = createTestUser();
        $chat = createTestChat($user);

        // Add some messages using factory states
        Message::factory()->for($chat)->user()->create(['parts' => ['text' => 'Hello, how are you?']]);
        Message::factory()->for($chat)->assistant()->create(['parts' => ['text' => 'I am doing well, thank you for asking! How can I help you today?']]);
        Message::factory()->for($chat)->user()->create(['parts' => ['text' => 'Can you explain how Laravel works?']]);
        Message::factory()->for($chat)->assistant()->create(['parts' => ['text' => 'Laravel is a PHP web application framework with expressive, elegant syntax. It provides tools for routing, authentication, sessions, caching, and more.']]);

        $this->actingAs($user);

        $page = visit('/chats/'.$chat->id);

        $page->assertSuccessful()
            ->assertNoJavaScriptErrors()
            ->screenshot('review/chats/show-with-messages-light');

        $page->inDarkMode()
            ->screenshot('review/chats/show-with-messages-dark');

        $page->on()->mobile()
            ->screenshot('review/chats/show-with-messages-mobile');
    });
});

/*
|--------------------------------------------------------------------------
| Settings Pages Review
|--------------------------------------------------------------------------
*/

describe('settings pages', function () {
    it('reviews profile settings', function () {
        $user = createTestUser();
        $this->actingAs($user);

        $page = visit('/settings/profile');

        $page->assertSuccessful()
            ->assertSee('Profile')
            ->assertNoJavaScriptErrors()
            ->screenshot('review/settings/profile-light');

        $page->inDarkMode()
            ->screenshot('review/settings/profile-dark');

        $page->on()->mobile()
            ->screenshot('review/settings/profile-mobile');
    });

    it('reviews password settings', function () {
        $user = createTestUser();
        $this->actingAs($user);

        $page = visit('/settings/password');

        $page->assertSuccessful()
            ->assertNoJavaScriptErrors()
            ->screenshot('review/settings/password-light');

        $page->inDarkMode()
            ->screenshot('review/settings/password-dark');
    });

    it('reviews appearance settings', function () {
        $user = createTestUser();
        $this->actingAs($user);

        $page = visit('/settings/appearance');

        $page->assertSuccessful()
            ->assertNoJavaScriptErrors()
            ->screenshot('review/settings/appearance-light');

        $page->inDarkMode()
            ->screenshot('review/settings/appearance-dark');
    });

    it('reviews two-factor settings', function () {
        $user = createTestUser();
        $this->actingAs($user);

        $page = visit('/settings/two-factor');

        $page->assertSuccessful()
            ->assertNoJavaScriptErrors()
            ->screenshot('review/settings/two-factor-light');

        $page->inDarkMode()
            ->screenshot('review/settings/two-factor-dark');
    });

    it('reviews provider settings - empty state', function () {
        $user = createTestUser();
        $this->actingAs($user);

        $page = visit('/settings/providers');

        $page->assertSuccessful()
            ->assertSee('No providers configured yet')
            ->assertNoJavaScriptErrors()
            ->screenshot('review/settings/providers-empty-light');

        $page->inDarkMode()
            ->screenshot('review/settings/providers-empty-dark');
    });

    it('reviews provider settings - with providers', function () {
        $user = createTestUser();
        UserApiCredential::factory()->for($user)->create(['provider' => 'openai']);
        UserApiCredential::factory()->for($user)->create(['provider' => 'anthropic']);

        $this->actingAs($user);

        $page = visit('/settings/providers');

        $page->assertSuccessful()
            ->assertNoJavaScriptErrors()
            ->screenshot('review/settings/providers-with-data-light');

        $page->inDarkMode()
            ->screenshot('review/settings/providers-with-data-dark');
    });
});

/*
|--------------------------------------------------------------------------
| Navigation Flow Review
|--------------------------------------------------------------------------
*/

describe('navigation flows', function () {
    it('reviews settings navigation flow', function () {
        $user = createTestUser();
        $this->actingAs($user);

        $page = visit('/settings/profile');

        // Navigate through all settings tabs
        $page->assertSee('Profile')
            ->screenshot('review/navigation/settings-1-profile');

        $page->click('Password')
            ->assertUrlContains('/settings/password')
            ->screenshot('review/navigation/settings-2-password');

        $page->click('Appearance')
            ->assertUrlContains('/settings/appearance')
            ->screenshot('review/navigation/settings-3-appearance');

        $page->click('Providers')
            ->assertUrlContains('/settings/providers')
            ->screenshot('review/navigation/settings-4-providers');

        $page->assertNoJavaScriptErrors();
    });

    it('reviews dashboard to chat flow', function () {
        $user = createTestUser();
        $chat = createTestChat($user);

        $this->actingAs($user);

        $page = visit('/dashboard');

        $page->assertSee('Dashboard')
            ->screenshot('review/navigation/flow-1-dashboard');

        $page->click('Chats')
            ->assertUrlContains('/chats')
            ->screenshot('review/navigation/flow-2-chats');

        $page->assertNoJavaScriptErrors();
    });
});

/*
|--------------------------------------------------------------------------
| Responsive Breakpoints Review
|--------------------------------------------------------------------------
*/

describe('responsive breakpoints', function () {
    it('reviews chat page at all breakpoints', function () {
        $user = createTestUser();
        $chat = createTestChat($user);

        $this->actingAs($user);

        $page = visit('/chats/'.$chat->id);

        // Desktop (1920px)
        $page->resize(1920, 1080)
            ->screenshot('review/responsive/chat-1920');

        // Laptop (1366px)
        $page->resize(1366, 768)
            ->screenshot('review/responsive/chat-1366');

        // Tablet landscape (1024px)
        $page->resize(1024, 768)
            ->screenshot('review/responsive/chat-1024');

        // Tablet portrait (768px)
        $page->resize(768, 1024)
            ->screenshot('review/responsive/chat-768');

        // Mobile large (425px)
        $page->resize(425, 896)
            ->screenshot('review/responsive/chat-425');

        // Mobile medium (375px)
        $page->resize(375, 812)
            ->screenshot('review/responsive/chat-375');

        // Mobile small (320px)
        $page->resize(320, 568)
            ->screenshot('review/responsive/chat-320');

        $page->assertNoJavaScriptErrors();
    });

    it('reviews dashboard at tablet breakpoint', function () {
        $user = createTestUser();
        $this->actingAs($user);

        $page = visit('/dashboard');

        $page->resize(768, 1024)
            ->assertNoJavaScriptErrors()
            ->screenshot('review/responsive/dashboard-tablet');
    });
});

/*
|--------------------------------------------------------------------------
| Accessibility Basics Review
|--------------------------------------------------------------------------
*/

describe('accessibility basics', function () {
    it('verifies login form has proper labels', function () {
        $page = visit('/login');

        // Check form elements have associated labels
        $page->assertScript('document.querySelector("label[for=\'email\']") !== null', true)
            ->assertScript('document.querySelector("label[for=\'password\']") !== null', true)
            ->assertNoJavaScriptErrors();
    });

    it('verifies chat input is keyboard accessible', function () {
        $user = createTestUser();
        $chat = createTestChat($user);

        $this->actingAs($user);

        $page = visit('/chats/'.$chat->id);

        // Tab to the message input and verify it's focusable
        $page->assertVisible('@message-input')
            ->click('@message-input')
            ->assertNoJavaScriptErrors();
    });

    it('verifies page has proper heading structure', function () {
        $user = createTestUser();
        $this->actingAs($user);

        $page = visit('/dashboard');

        // Check that there's at least one h1
        $page->assertScript('document.querySelector("h1") !== null', true)
            ->assertNoJavaScriptErrors();
    });
});

/*
|--------------------------------------------------------------------------
| Error State Review
|--------------------------------------------------------------------------
*/

describe('error states', function () {
    it('reviews 404 page', function () {
        $user = createTestUser();
        $this->actingAs($user);

        $page = visit('/this-page-does-not-exist-12345');

        $page->screenshot('review/errors/404-light');

        $page->inDarkMode()
            ->screenshot('review/errors/404-dark');
    });
});

/*
|--------------------------------------------------------------------------
| Performance Check
|--------------------------------------------------------------------------
*/

describe('performance', function () {
    it('verifies pages load within acceptable time', function () {
        $user = createTestUser();
        $this->actingAs($user);

        $startTime = microtime(true);
        $page = visit('/dashboard');
        $loadTime = microtime(true) - $startTime;

        $page->assertNoJavaScriptErrors();

        // Page should load in under 5 seconds
        expect($loadTime)->toBeLessThan(5.0);
    });
});
