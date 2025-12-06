<?php

declare(strict_types=1);

use App\Models\Chat;
use App\Models\User;
use App\Policies\ChatPolicy;

describe('ChatPolicy', function () {
    beforeEach(function () {
        $this->policy = new ChatPolicy;
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    });

    describe('viewAny', function () {
        it('allows any authenticated user to view any chats', function () {
            expect($this->policy->viewAny($this->user))->toBeTrue();
        });
    });

    describe('view', function () {
        it('allows owner to view their chat', function () {
            $chat = Chat::factory()->for($this->user)->create();

            expect($this->policy->view($this->user, $chat))->toBeTrue();
        });

        it('denies other users from viewing the chat', function () {
            $chat = Chat::factory()->for($this->user)->create();

            expect($this->policy->view($this->otherUser, $chat))->toBeFalse();
        });
    });

    describe('create', function () {
        it('allows any authenticated user to create chats', function () {
            expect($this->policy->create($this->user))->toBeTrue();
        });
    });

    describe('update', function () {
        it('allows owner to update their chat', function () {
            $chat = Chat::factory()->for($this->user)->create();

            expect($this->policy->update($this->user, $chat))->toBeTrue();
        });

        it('denies other users from updating the chat', function () {
            $chat = Chat::factory()->for($this->user)->create();

            expect($this->policy->update($this->otherUser, $chat))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('allows owner to delete their chat', function () {
            $chat = Chat::factory()->for($this->user)->create();

            expect($this->policy->delete($this->user, $chat))->toBeTrue();
        });

        it('denies other users from deleting the chat', function () {
            $chat = Chat::factory()->for($this->user)->create();

            expect($this->policy->delete($this->otherUser, $chat))->toBeFalse();
        });
    });

    describe('stream', function () {
        it('allows owner to stream to their chat', function () {
            $chat = Chat::factory()->for($this->user)->create();

            expect($this->policy->stream($this->user, $chat))->toBeTrue();
        });

        it('denies other users from streaming to the chat', function () {
            $chat = Chat::factory()->for($this->user)->create();

            expect($this->policy->stream($this->otherUser, $chat))->toBeFalse();
        });
    });
});
