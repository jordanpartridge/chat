<?php

declare(strict_types=1);

use App\Http\Requests\StoreAgentRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

describe('authorization', function () {
    it('authorizes all authenticated users', function () {
        $user = User::factory()->create();

        $request = new StoreAgentRequest;
        $request->setUserResolver(fn () => $user);

        expect($request->authorize())->toBeTrue();
    });
});

describe('required field validation', function () {
    it('requires name field', function () {
        $request = new StoreAgentRequest;
        $validator = Validator::make([], $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('name'))->toBeTrue();
    });

    it('requires description field', function () {
        $request = new StoreAgentRequest;
        $validator = Validator::make(['name' => 'Test Agent'], $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('description'))->toBeTrue();
    });

    it('validates name max length', function () {
        $request = new StoreAgentRequest;
        $validator = Validator::make([
            'name' => str_repeat('a', 300),
            'description' => 'Valid description',
        ], $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('name'))->toBeTrue();
    });
});

describe('optional field validation', function () {
    it('allows nullable system_prompt', function () {
        $request = new StoreAgentRequest;
        $validator = Validator::make([
            'name' => 'Test Agent',
            'description' => 'Valid description',
            'system_prompt' => null,
        ], $request->rules());

        expect($validator->errors()->has('system_prompt'))->toBeFalse();
    });

    it('allows nullable avatar', function () {
        $request = new StoreAgentRequest;
        $validator = Validator::make([
            'name' => 'Test Agent',
            'description' => 'Valid description',
            'avatar' => null,
        ], $request->rules());

        expect($validator->errors()->has('avatar'))->toBeFalse();
    });
});

describe('array field validation', function () {
    it('validates tools as array', function () {
        $request = new StoreAgentRequest;
        $validator = Validator::make([
            'name' => 'Test Agent',
            'description' => 'Valid description',
            'tools' => 'not-an-array',
        ], $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('tools'))->toBeTrue();
    });

    it('validates capabilities as array', function () {
        $request = new StoreAgentRequest;
        $validator = Validator::make([
            'name' => 'Test Agent',
            'description' => 'Valid description',
            'capabilities' => 'not-an-array',
        ], $request->rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('capabilities'))->toBeTrue();
    });
});

describe('valid data', function () {
    it('passes with valid data', function () {
        $request = new StoreAgentRequest;
        $validator = Validator::make([
            'name' => 'Test Agent',
            'description' => 'A helpful assistant',
            'system_prompt' => 'You are a helpful assistant',
            'avatar' => '🤖',
            'tools' => ['search_web', 'create_artifact'],
            'capabilities' => ['code_generation', 'analysis'],
        ], $request->rules());

        expect($validator->fails())->toBeFalse();
    });
});
