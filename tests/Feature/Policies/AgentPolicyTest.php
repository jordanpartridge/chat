<?php

declare(strict_types=1);

use App\Models\Agent;
use App\Models\User;
use App\Policies\AgentPolicy;

describe('AgentPolicy', function () {
    beforeEach(function () {
        $this->policy = new AgentPolicy;
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    });

    describe('viewAny', function () {
        it('allows any authenticated user to view any agents', function () {
            expect($this->policy->viewAny($this->user))->toBeTrue();
        });
    });

    describe('view', function () {
        it('allows owner to view their agent', function () {
            $agent = Agent::factory()->for($this->user)->create();

            expect($this->policy->view($this->user, $agent))->toBeTrue();
        });

        it('denies other users from viewing the agent', function () {
            $agent = Agent::factory()->for($this->user)->create();

            expect($this->policy->view($this->otherUser, $agent))->toBeFalse();
        });
    });

    describe('create', function () {
        it('allows any authenticated user to create agents', function () {
            expect($this->policy->create($this->user))->toBeTrue();
        });
    });

    describe('update', function () {
        it('allows owner to update their agent', function () {
            $agent = Agent::factory()->for($this->user)->create();

            expect($this->policy->update($this->user, $agent))->toBeTrue();
        });

        it('denies other users from updating the agent', function () {
            $agent = Agent::factory()->for($this->user)->create();

            expect($this->policy->update($this->otherUser, $agent))->toBeFalse();
        });
    });

    describe('delete', function () {
        it('allows owner to delete their agent', function () {
            $agent = Agent::factory()->for($this->user)->create();

            expect($this->policy->delete($this->user, $agent))->toBeTrue();
        });

        it('denies other users from deleting the agent', function () {
            $agent = Agent::factory()->for($this->user)->create();

            expect($this->policy->delete($this->otherUser, $agent))->toBeFalse();
        });
    });
});
