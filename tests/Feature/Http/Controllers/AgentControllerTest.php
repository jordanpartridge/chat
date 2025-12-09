<?php

use App\Models\Agent;
use App\Models\AiModel;
use App\Models\User;

describe('index', function () {
    it('redirects guests to login', function () {
        $response = $this->get(route('agents.index'));

        $response->assertRedirect(route('login'));
    });

    it('displays agents for authenticated users', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('agents.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Agents/Index')
            ->has('agents')
        );
    });

    it('shows user agents and system agents', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $userAgent = Agent::factory()->for($user)->create();
        $systemAgent = Agent::factory()->system()->create();
        $otherUserAgent = Agent::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->get(route('agents.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Agents/Index')
            ->has('agents', 2)
        );
    });
});

describe('create', function () {
    it('redirects guests to login', function () {
        $response = $this->get(route('agents.create'));

        $response->assertRedirect(route('login'));
    });

    it('displays the create form', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('agents.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Agents/Create')
            ->has('models')
            ->has('availableTools')
            ->has('availableCapabilities')
        );
    });
});

describe('store', function () {
    it('creates an agent and redirects to show', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('agents.store'), [
            'name' => 'Test Agent',
            'description' => 'A test agent for testing',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('agents', [
            'user_id' => $user->id,
            'name' => 'Test Agent',
        ]);
    });

    it('creates an agent with tools and capabilities', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('agents.store'), [
            'name' => 'Test Agent',
            'description' => 'A test agent',
            'tools' => ['web_search', 'code_interpreter'],
            'capabilities' => ['reasoning', 'coding'],
        ]);

        $response->assertRedirect();
        $agent = Agent::where('user_id', $user->id)->first();
        expect($agent->tools)->toBe(['web_search', 'code_interpreter']);
        expect($agent->capabilities)->toBe(['reasoning', 'coding']);
    });

    it('creates an agent with a default model', function () {
        $user = User::factory()->create();
        $aiModel = AiModel::factory()->create();

        $response = $this->actingAs($user)->post(route('agents.store'), [
            'name' => 'Test Agent',
            'description' => 'A test agent',
            'default_model_id' => $aiModel->id,
        ]);

        $response->assertRedirect();
        $agent = Agent::where('user_id', $user->id)->first();
        expect($agent->default_model_id)->toBe($aiModel->id);
    });

    it('requires name field', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('agents.store'), [
            'description' => 'A test agent',
        ]);

        $response->assertSessionHasErrors('name');
    });

    it('requires description field', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('agents.store'), [
            'name' => 'Test Agent',
        ]);

        $response->assertSessionHasErrors('description');
    });
});

describe('show', function () {
    it('redirects guests to login', function () {
        $agent = Agent::factory()->create();

        $response = $this->get(route('agents.show', $agent));

        $response->assertRedirect(route('login'));
    });

    it('displays the agent for the owner', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('agents.show', $agent));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Agents/Show')
            ->has('agent')
            ->has('models')
            ->where('agent.id', $agent->id)
        );
    });

    it('displays system agents to any authenticated user', function () {
        $user = User::factory()->create();
        $systemAgent = Agent::factory()->system()->create();

        $response = $this->actingAs($user)->get(route('agents.show', $systemAgent));

        $response->assertOk();
    });

    it('forbids viewing another user\'s agent', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $agent = Agent::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->get(route('agents.show', $agent));

        $response->assertForbidden();
    });
});

describe('edit', function () {
    it('displays the edit form for the owner', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('agents.edit', $agent));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Agents/Edit')
            ->has('agent')
            ->has('models')
            ->where('agent.id', $agent->id)
        );
    });

    it('forbids editing another user\'s agent', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $agent = Agent::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->get(route('agents.edit', $agent));

        $response->assertForbidden();
    });

    it('forbids editing system agents', function () {
        $user = User::factory()->create();
        $systemAgent = Agent::factory()->system()->create();

        $response = $this->actingAs($user)->get(route('agents.edit', $systemAgent));

        $response->assertForbidden();
    });
});

describe('update', function () {
    it('updates the agent', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->for($user)->create(['name' => 'Original Name']);

        $response = $this->actingAs($user)->patch(route('agents.update', $agent), [
            'name' => 'New Name',
            'description' => 'Updated description',
        ]);

        $response->assertRedirect();
        expect($agent->fresh()->name)->toBe('New Name');
    });

    it('updates the agent tools and capabilities', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(route('agents.update', $agent), [
            'name' => $agent->name,
            'description' => $agent->description,
            'tools' => ['web_search'],
            'capabilities' => ['analysis'],
        ]);

        $response->assertRedirect();
        expect($agent->fresh()->tools)->toBe(['web_search']);
        expect($agent->fresh()->capabilities)->toBe(['analysis']);
    });

    it('forbids updating another user\'s agent', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $agent = Agent::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->patch(route('agents.update', $agent), [
            'name' => 'Hacked Name',
            'description' => 'Hacked description',
        ]);

        $response->assertForbidden();
    });

    it('forbids updating system agents', function () {
        $user = User::factory()->create();
        $systemAgent = Agent::factory()->system()->create();

        $response = $this->actingAs($user)->patch(route('agents.update', $systemAgent), [
            'name' => 'Hacked System',
            'description' => 'Hacked',
        ]);

        $response->assertForbidden();
    });
});

describe('destroy', function () {
    it('deletes the agent and redirects to index', function () {
        $user = User::factory()->create();
        $agent = Agent::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete(route('agents.destroy', $agent));

        $response->assertRedirect(route('agents.index'));
        $this->assertDatabaseMissing('agents', ['id' => $agent->id]);
    });

    it('forbids deleting another user\'s agent', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $agent = Agent::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->delete(route('agents.destroy', $agent));

        $response->assertForbidden();
        $this->assertDatabaseHas('agents', ['id' => $agent->id]);
    });

    it('forbids deleting system agents', function () {
        $user = User::factory()->create();
        $systemAgent = Agent::factory()->system()->create();

        $response = $this->actingAs($user)->delete(route('agents.destroy', $systemAgent));

        $response->assertForbidden();
        $this->assertDatabaseHas('agents', ['id' => $systemAgent->id]);
    });
});
