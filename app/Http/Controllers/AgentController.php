<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Models\Agent;
use App\Models\AiModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function index(Request $request): Response
    {
        $agents = Agent::query()
            ->where(function ($query) use ($request) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $request->user()->id);
            })
            ->with('defaultModel')
            ->orderByDesc('updated_at')
            ->get();

        return Inertia::render('Agents/Index', [
            'agents' => $agents,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Agents/Create', [
            'models' => AiModel::query()->enabled()->get(),
            'availableTools' => $this->getAvailableTools(),
            'availableCapabilities' => $this->getAvailableCapabilities(),
        ]);
    }

    public function store(StoreAgentRequest $request): RedirectResponse
    {
        $agent = $request->user()->agents()->create($request->validated());

        return to_route('agents.show', $agent);
    }

    public function show(Request $request, Agent $agent): Response
    {
        abort_unless(
            $agent->user_id === null || $agent->user_id === $request->user()->id,
            403
        );

        return Inertia::render('Agents/Show', [
            'agent' => $agent->load('defaultModel'),
            'models' => AiModel::query()->enabled()->get(),
            'availableTools' => $this->getAvailableTools(),
            'availableCapabilities' => $this->getAvailableCapabilities(),
        ]);
    }

    public function edit(Request $request, Agent $agent): Response
    {
        abort_unless($agent->user_id === $request->user()->id, 403);

        return Inertia::render('Agents/Edit', [
            'agent' => $agent->load('defaultModel'),
            'models' => AiModel::query()->enabled()->get(),
            'availableTools' => $this->getAvailableTools(),
            'availableCapabilities' => $this->getAvailableCapabilities(),
        ]);
    }

    public function update(UpdateAgentRequest $request, Agent $agent): RedirectResponse
    {
        $agent->update($request->validated());

        return to_route('agents.show', $agent);
    }

    public function destroy(Request $request, Agent $agent): RedirectResponse
    {
        abort_unless($agent->user_id === $request->user()->id, 403);

        $agent->delete();

        return to_route('agents.index');
    }

    /**
     * @return array<int, array{id: string, name: string, description: string}>
     */
    private function getAvailableTools(): array
    {
        return [
            ['id' => 'web_search', 'name' => 'Web Search', 'description' => 'Search the web for information'],
            ['id' => 'code_interpreter', 'name' => 'Code Interpreter', 'description' => 'Execute and analyze code'],
            ['id' => 'file_reader', 'name' => 'File Reader', 'description' => 'Read and parse files'],
            ['id' => 'knowledge_base', 'name' => 'Knowledge Base', 'description' => 'Search internal knowledge base'],
        ];
    }

    /**
     * @return array<int, array{id: string, name: string, description: string}>
     */
    private function getAvailableCapabilities(): array
    {
        return [
            ['id' => 'reasoning', 'name' => 'Advanced Reasoning', 'description' => 'Complex problem solving'],
            ['id' => 'creativity', 'name' => 'Creative Writing', 'description' => 'Generate creative content'],
            ['id' => 'analysis', 'name' => 'Data Analysis', 'description' => 'Analyze and interpret data'],
            ['id' => 'coding', 'name' => 'Code Generation', 'description' => 'Write and review code'],
        ];
    }
}
