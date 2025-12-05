<?php

use App\Models\Artifact;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;

describe('index', function () {
    it('redirects guests to login', function () {
        $chat = Chat::factory()->create();

        $response = $this->get(route('artifacts.index', $chat));

        $response->assertRedirect(route('login'));
    });

    it('forbids accessing artifacts for another user\'s chat', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $chat = Chat::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->get(route('artifacts.index', $chat));

        $response->assertForbidden();
    });

    it('returns artifacts for a chat', function () {
        $user = User::factory()->create();
        $chat = Chat::factory()->for($user)->create();
        $message = Message::factory()->for($chat)->create();
        $artifact = Artifact::factory()->for($message)->create();

        $response = $this->actingAs($user)->get(route('artifacts.index', $chat));

        $response->assertOk();
        $response->assertJson([
            ['id' => $artifact->id, 'title' => $artifact->title],
        ]);
    });

    it('returns empty array when chat has no artifacts', function () {
        $user = User::factory()->create();
        $chat = Chat::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('artifacts.index', $chat));

        $response->assertOk();
        $response->assertJson([]);
    });
});

describe('show', function () {
    it('redirects guests to login', function () {
        $artifact = Artifact::factory()->create();

        $response = $this->get(route('artifacts.show', $artifact));

        $response->assertRedirect(route('login'));
    });

    it('forbids viewing another user\'s artifact', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $chat = Chat::factory()->for($otherUser)->create();
        $message = Message::factory()->for($chat)->create();
        $artifact = Artifact::factory()->for($message)->create();

        $response = $this->actingAs($user)->get(route('artifacts.show', $artifact));

        $response->assertForbidden();
    });

    it('returns artifact json for owner', function () {
        $user = User::factory()->create();
        $chat = Chat::factory()->for($user)->create();
        $message = Message::factory()->for($chat)->create();
        $artifact = Artifact::factory()->for($message)->create();

        $response = $this->actingAs($user)->get(route('artifacts.show', $artifact));

        $response->assertOk();
        $response->assertJson([
            'id' => $artifact->id,
            'title' => $artifact->title,
            'content' => $artifact->content,
        ]);
    });
});

describe('render', function () {
    it('redirects guests to login', function () {
        $artifact = Artifact::factory()->create();

        $response = $this->get(route('artifacts.render', $artifact));

        $response->assertRedirect(route('login'));
    });

    it('forbids rendering another user\'s artifact', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $chat = Chat::factory()->for($otherUser)->create();
        $message = Message::factory()->for($chat)->create();
        $artifact = Artifact::factory()->for($message)->create();

        $response = $this->actingAs($user)->get(route('artifacts.render', $artifact));

        $response->assertForbidden();
    });

    it('renders code artifact with correct headers', function () {
        $user = User::factory()->create();
        $chat = Chat::factory()->for($user)->create();
        $message = Message::factory()->for($chat)->create();
        $artifact = Artifact::factory()->for($message)->code()->create();

        $response = $this->actingAs($user)->get(route('artifacts.render', $artifact));

        $response->assertOk();
        expect($response->headers->get('Content-Type'))->toContain('text/html');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        expect($response->headers->get('Content-Security-Policy'))->toContain("default-src 'self'");
    });

    it('renders markdown artifact with correct view', function () {
        $user = User::factory()->create();
        $chat = Chat::factory()->for($user)->create();
        $message = Message::factory()->for($chat)->create();
        $artifact = Artifact::factory()->for($message)->markdown()->create();

        $response = $this->actingAs($user)->get(route('artifacts.render', $artifact));

        $response->assertOk();
        expect($response->headers->get('Content-Type'))->toContain('text/html');
    });

    it('renders html artifact with correct CSP', function () {
        $user = User::factory()->create();
        $chat = Chat::factory()->for($user)->create();
        $message = Message::factory()->for($chat)->create();
        $artifact = Artifact::factory()->for($message)->html()->create();

        $response = $this->actingAs($user)->get(route('artifacts.render', $artifact));

        $response->assertOk();
        expect($response->headers->get('Content-Security-Policy'))->toContain("'unsafe-inline'");
    });

    it('renders svg artifact with restrictive CSP', function () {
        $user = User::factory()->create();
        $chat = Chat::factory()->for($user)->create();
        $message = Message::factory()->for($chat)->create();
        $artifact = Artifact::factory()->for($message)->svg()->create();

        $response = $this->actingAs($user)->get(route('artifacts.render', $artifact));

        $response->assertOk();
        expect($response->headers->get('Content-Security-Policy'))->toContain("script-src 'none'");
    });

    it('renders mermaid artifact correctly', function () {
        $user = User::factory()->create();
        $chat = Chat::factory()->for($user)->create();
        $message = Message::factory()->for($chat)->create();
        $artifact = Artifact::factory()->for($message)->mermaid()->create();

        $response = $this->actingAs($user)->get(route('artifacts.render', $artifact));

        $response->assertOk();
        expect($response->headers->get('Content-Security-Policy'))->toContain('cdn.jsdelivr.net');
    });

    it('renders react artifact with unsafe-eval CSP', function () {
        $user = User::factory()->create();
        $chat = Chat::factory()->for($user)->create();
        $message = Message::factory()->for($chat)->create();
        $artifact = Artifact::factory()->for($message)->react()->create();

        $response = $this->actingAs($user)->get(route('artifacts.render', $artifact));

        $response->assertOk();
        expect($response->headers->get('Content-Security-Policy'))->toContain("'unsafe-eval'");
    });

    it('renders vue artifact with unsafe-eval CSP', function () {
        $user = User::factory()->create();
        $chat = Chat::factory()->for($user)->create();
        $message = Message::factory()->for($chat)->create();
        $artifact = Artifact::factory()->for($message)->vue()->create();

        $response = $this->actingAs($user)->get(route('artifacts.render', $artifact));

        $response->assertOk();
        expect($response->headers->get('Content-Security-Policy'))->toContain("'unsafe-eval'");
    });

    it('renders unknown artifact type using code renderer as fallback', function () {
        $user = User::factory()->create();
        $chat = Chat::factory()->for($user)->create();
        $message = Message::factory()->for($chat)->create();
        $artifact = Artifact::factory()->for($message)->create(['type' => 'unknown_type']);

        $response = $this->actingAs($user)->get(route('artifacts.render', $artifact));

        $response->assertOk();
        expect($response->headers->get('Content-Security-Policy'))->toContain("default-src 'self'");
    });
});
