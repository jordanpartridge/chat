<?php

namespace App\Http\Controllers;

use App\Models\AgentSwarm;
use App\Models\AgentSwarmMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentSwarmController extends Controller
{
    /**
     * Display the swarm dashboard.
     */
    public function index(Request $request): Response
    {
        $swarms = AgentSwarm::query()
            ->where('user_id', $request->user()->id)
            ->with(['members.agent'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($swarm) {
                return [
                    'id' => $swarm->id,
                    'name' => $swarm->name,
                    'description' => $swarm->description,
                    'status' => $swarm->status,
                    'total_agents' => $swarm->total_agents,
                    'completed_agents' => $swarm->completed_agents,
                    'failed_agents' => $swarm->failed_agents,
                    'progress_percentage' => $swarm->progress_percentage,
                    'started_at' => $swarm->started_at?->toIso8601String(),
                    'completed_at' => $swarm->completed_at?->toIso8601String(),
                    'members' => $swarm->members->map(function ($member) {
                        return [
                            'id' => $member->id,
                            'name' => $member->name,
                            'role' => $member->role,
                            'status' => $member->status,
                            'progress' => $member->progress,
                            'current_task' => $member->current_task,
                            'error' => $member->error,
                            'started_at' => $member->started_at?->toIso8601String(),
                            'completed_at' => $member->completed_at?->toIso8601String(),
                        ];
                    }),
                ];
            });

        return Inertia::render('Agents/Swarm', [
            'swarms' => $swarms,
        ]);
    }

    /**
     * Get swarm details by ID.
     */
    public function show(Request $request, AgentSwarm $swarm): JsonResponse
    {
        abort_unless($swarm->user_id === $request->user()->id, 403);

        return response()->json([
            'id' => $swarm->id,
            'name' => $swarm->name,
            'description' => $swarm->description,
            'status' => $swarm->status,
            'total_agents' => $swarm->total_agents,
            'completed_agents' => $swarm->completed_agents,
            'failed_agents' => $swarm->failed_agents,
            'progress_percentage' => $swarm->progress_percentage,
            'started_at' => $swarm->started_at?->toIso8601String(),
            'completed_at' => $swarm->completed_at?->toIso8601String(),
            'members' => $swarm->members->map(function ($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'role' => $member->role,
                    'status' => $member->status,
                    'progress' => $member->progress,
                    'current_task' => $member->current_task,
                    'error' => $member->error,
                    'started_at' => $member->started_at?->toIso8601String(),
                    'completed_at' => $member->completed_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Update agent status within a swarm.
     */
    public function updateAgentStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'swarm_id' => 'required|exists:agent_swarms,id',
            'agent_id' => 'required|exists:agent_swarm_members,id',
            'status' => 'required|in:pending,running,completed,failed',
            'progress' => 'nullable|integer|min:0|max:100',
            'current_task' => 'nullable|string',
            'error' => 'nullable|string',
        ]);

        $swarm = AgentSwarm::findOrFail($validated['swarm_id']);
        abort_unless($swarm->user_id === $request->user()->id, 403);

        $member = AgentSwarmMember::findOrFail($validated['agent_id']);

        $member->update([
            'status' => $validated['status'],
            'progress' => $validated['progress'] ?? $member->progress,
            'current_task' => $validated['current_task'] ?? $member->current_task,
            'error' => $validated['error'] ?? $member->error,
            'started_at' => $member->started_at ?? ($validated['status'] === 'running' ? now() : null),
            'completed_at' => in_array($validated['status'], ['completed', 'failed']) ? now() : null,
        ]);

        // Update swarm counts
        $this->updateSwarmCounts($swarm);

        return response()->json(['success' => true]);
    }

    /**
     * Create a new swarm.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'agents' => 'required|array|min:1',
            'agents.*.agent_id' => 'required|exists:agents,id',
            'agents.*.name' => 'required|string',
            'agents.*.role' => 'nullable|string',
        ]);

        $swarm = AgentSwarm::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'user_id' => $request->user()->id,
            'status' => 'pending',
            'total_agents' => count($validated['agents']),
        ]);

        foreach ($validated['agents'] as $agentData) {
            AgentSwarmMember::create([
                'swarm_id' => $swarm->id,
                'agent_id' => $agentData['agent_id'],
                'name' => $agentData['name'],
                'role' => $agentData['role'] ?? null,
                'status' => 'pending',
            ]);
        }

        return response()->json(['swarm_id' => $swarm->id], 201);
    }

    /**
     * Update swarm counts based on member statuses.
     */
    private function updateSwarmCounts(AgentSwarm $swarm): void
    {
        $completedCount = $swarm->members()->where('status', 'completed')->count();
        $failedCount = $swarm->members()->where('status', 'failed')->count();

        $swarm->update([
            'completed_agents' => $completedCount,
            'failed_agents' => $failedCount,
            'status' => $this->determineSwarmStatus($swarm, $completedCount, $failedCount),
            'started_at' => $swarm->started_at ?? ($swarm->members()->where('status', 'running')->exists() ? now() : null),
            'completed_at' => ($completedCount + $failedCount === $swarm->total_agents) ? now() : null,
        ]);
    }

    /**
     * Determine swarm status based on member statuses.
     */
    private function determineSwarmStatus(AgentSwarm $swarm, int $completedCount, int $failedCount): string
    {
        if ($completedCount + $failedCount === $swarm->total_agents) {
            return $failedCount > 0 ? 'failed' : 'completed';
        }

        if ($swarm->members()->where('status', 'running')->exists()) {
            return 'running';
        }

        return 'pending';
    }
}
