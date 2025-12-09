<?php

declare(strict_types=1);

use App\Models\Agent;
use App\Models\AiModel;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('can view the agents index page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/agents');

    $page->assertSee('Agents')
        ->assertSee('Create Agent')
        ->assertNoJavaScriptErrors();
});

it('shows empty state when no agents exist', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/agents');

    $page->assertSee('No agents yet')
        ->assertSee('Create your first custom AI agent')
        ->assertNoJavaScriptErrors();
});

it('can view the create agent page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/agents/create');

    $page->assertSee('Create Agent')
        ->assertSee('Basic Information')
        ->assertSee('System Prompt')
        ->assertSee('Tools')
        ->assertSee('Capabilities')
        ->assertNoJavaScriptErrors();
});

it('can create a new agent', function (): void {
    $user = User::factory()->create();
    AiModel::factory()->create(['enabled' => true]);
    $this->actingAs($user);

    $page = visit('/agents/create');

    $page->fill('name', 'My Test Agent')
        ->fill('description', 'A helpful assistant for testing')
        ->fill('system_prompt', 'You are a helpful assistant.')
        ->click('Create Agent')
        ->assertUrlContains('/agents/')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('agents', [
        'user_id' => $user->id,
        'name' => 'My Test Agent',
    ]);
});

it('can view an agent', function (): void {
    $user = User::factory()->create();
    $agent = Agent::factory()->for($user)->create([
        'name' => 'Test Agent',
        'description' => 'A test agent description',
    ]);
    $this->actingAs($user);

    $page = visit("/agents/{$agent->id}");

    $page->assertSee('Test Agent')
        ->assertSee('A test agent description')
        ->assertSee('Edit')
        ->assertNoJavaScriptErrors();
});

it('can view the edit agent page', function (): void {
    $user = User::factory()->create();
    $agent = Agent::factory()->for($user)->create(['name' => 'Editable Agent']);
    $this->actingAs($user);

    $page = visit("/agents/{$agent->id}/edit");

    $page->assertSee('Edit Editable Agent')
        ->assertNoJavaScriptErrors();
});

it('can update an agent', function (): void {
    $user = User::factory()->create();
    $agent = Agent::factory()->for($user)->create(['name' => 'Original Name']);
    $this->actingAs($user);

    $page = visit("/agents/{$agent->id}/edit");

    $page->fill('name', 'Updated Name')
        ->click('Save Changes')
        ->assertUrlContains('/agents/')
        ->assertNoJavaScriptErrors();

    expect($agent->fresh()->name)->toBe('Updated Name');
});

it('displays system agents in the list', function (): void {
    $user = User::factory()->create();
    Agent::factory()->system()->create(['name' => 'System Helper']);
    $this->actingAs($user);

    $page = visit('/agents');

    $page->assertSee('System Helper')
        ->assertSee('System')
        ->assertNoJavaScriptErrors();
});

it('displays custom agents in the list', function (): void {
    $user = User::factory()->create();
    Agent::factory()->for($user)->create(['name' => 'My Custom Agent']);
    $this->actingAs($user);

    $page = visit('/agents');

    $page->assertSee('My Custom Agent')
        ->assertSee('Custom')
        ->assertNoJavaScriptErrors();
});

it('cannot edit system agents', function (): void {
    $user = User::factory()->create();
    $systemAgent = Agent::factory()->system()->create();
    $this->actingAs($user);

    $page = visit("/agents/{$systemAgent->id}");

    // Edit button should not be present for system agents
    $page->assertDontSee('Edit')
        ->assertNoJavaScriptErrors();
});
