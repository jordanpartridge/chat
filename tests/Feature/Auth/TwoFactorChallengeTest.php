<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

describe('two factor challenge authentication', function () {
    test('two factor challenge redirects to login when not authenticated', function () {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        $response = $this->get(route('two-factor.login'));

        $response->assertRedirect(route('login'));
    });

    test('two factor challenge can be rendered', function () {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->create();

        $user->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->get(route('two-factor.login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/TwoFactorChallenge')
            );
    });
});

describe('two factor challenge rate limiting', function () {
    test('two factor challenge is rate limited', function () {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->create();

        $user->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        // Log in to set up the two-factor challenge
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Submit invalid codes to trigger rate limiting
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('two-factor.login'), [
                'code' => '000000',
            ]);
        }

        // The 6th attempt should be rate limited
        $response = $this->post(route('two-factor.login'), [
            'code' => '000000',
        ]);

        $response->assertStatus(429);
    });
});
