<?php

declare(strict_types=1);

use App\Http\Requests\UpdateAgentRequest;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

describe('authorization', function () {
    it('authorizes owner to update their agent', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $request = new UpdateAgentRequest;
        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(fn () => new class($agent)
        {
            public function __construct(private Agent $agent) {}

            public function parameter($key)
            {
                return $this->agent;
            }
        });

        expect($request->authorize())->toBeTrue();
    });

    it('denies update for system agents (null user_id)', function () {
        $user = User::factory()->create();
        $systemAgent = Agent::factory()->create(['user_id' => null]);

        $request = new UpdateAgentRequest;
        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(fn () => new class($systemAgent)
        {
            public function __construct(private Agent $agent) {}

            public function parameter($key)
            {
                return $this->agent;
            }
        });

        expect($request->authorize())->toBeFalse();
    });

    it('denies update for other users agents', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherAgent = Agent::factory()->create(['user_id' => $otherUser->id]);

        $request = new UpdateAgentRequest;
        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(fn () => new class($otherAgent)
        {
            public function __construct(private Agent $agent) {}

            public function parameter($key)
            {
                return $this->agent;
            }
        });

        expect($request->authorize())->toBeFalse();
    });

    it('denies when agent is not found', function () {
        $user = User::factory()->create();

        $request = new UpdateAgentRequest;
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
});

describe('required field validation', function () {
    it('requires name field', function () {
        $request = new UpdateAgentRequest;
        $validator = Validator::make([], $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('name'))->toBeTrue();
    });

    it('requires description field', function () {
        $request = new UpdateAgentRequest;
        $validator = Validator::make(['name' => 'Test Agent'], $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('description'))->toBeTrue();
    });
});

describe('field type validation', function () {
    it('validates is_active as boolean', function () {
        $request = new UpdateAgentRequest;
        $validator = Validator::make([
            'name' => 'Test Agent',
            'description' => 'Valid description',
            'is_active' => 'not-a-boolean',
        ], $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('is_active'))->toBeTrue();
    });
});

describe('valid data', function () {
    it('passes with valid update data', function () {
        $request = new UpdateAgentRequest;
        $validator = Validator::make([
            'name' => 'Updated Agent',
            'description' => 'Updated description',
            'system_prompt' => 'Updated prompt',
            'is_active' => true,
        ], $request->rules());

        expect($validator->fails())->toBeFalse();
    });
});
