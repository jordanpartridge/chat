<?php

namespace App\Console\Commands;

use App\Services\AgentStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AgentRunCommand extends Command
{
    protected $signature = 'agent:run
        {--swarm= : Swarm ID this agent belongs to}
        {--agent= : Agent ID}
        {--role= : Agent role (architect, implementer, tester, reviewer)}';

    protected $description = 'Execute an agent work session';

    protected string $promptsDir;

    public function __construct(
        protected AgentStatusService $statusService
    ) {
        parent::__construct();
        $this->promptsDir = resource_path('prompts');
    }

    public function handle(): int
    {
        $swarmId = $this->option('swarm');
        $agentId = $this->option('agent');
        $role = $this->option('role');

        if (!$swarmId || !$agentId || !$role) {
            $this->error('Missing required options: --swarm, --agent, --role');
            return self::FAILURE;
        }

        $status = $this->statusService->get($agentId);

        if (!$status) {
            $this->error("Agent status not found: {$agentId}");
            return self::FAILURE;
        }

        // Load the prompt for this role
        $promptFile = "{$this->promptsDir}/{$role}.md";
        if (!File::exists($promptFile)) {
            $this->statusService->fail($agentId, "Prompt file not found: {$role}.md");
            return self::FAILURE;
        }

        $prompt = File::get($promptFile);

        try {
            // Update status to running
            $this->statusService->setStatus($agentId, 'running');
            $this->statusService->setProgress($agentId, 0);

            // Simulate work with progress updates
            // In production, this would connect to Claude API
            $this->executeWork($agentId, $prompt, $role);

            // Mark as completed
            $this->statusService->complete($agentId, $this->generateOutput($role));

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->statusService->fail($agentId, $e->getMessage());

            return self::FAILURE;
        }
    }

    protected function executeWork(string $agentId, string $prompt, string $role): void
    {
        // Simulate work phases with progress updates
        $phases = [
            ['progress' => 25, 'phase' => 'Analyzing task', 'delay' => 2],
            ['progress' => 50, 'phase' => 'Processing context', 'delay' => 3],
            ['progress' => 75, 'phase' => 'Generating solution', 'delay' => 3],
            ['progress' => 90, 'phase' => 'Finalizing output', 'delay' => 2],
        ];

        foreach ($phases as $phase) {
            sleep($phase['delay']);

            $this->statusService->update($agentId, [
                'progress' => $phase['progress'],
                'current_phase' => $phase['phase'],
            ]);
        }
    }

    protected function generateOutput(string $role): string
    {
        // Simulate role-specific output
        // In production, this would be the actual Claude response
        return match ($role) {
            'architect' => "Architecture analysis complete:\n- Reviewed codebase structure\n- Identified key components\n- Proposed implementation plan",
            'implementer' => "Implementation complete:\n- Added new features\n- Updated tests\n- Verified functionality",
            'tester' => "Testing complete:\n- All tests passing\n- Code coverage: 95%\n- No critical issues found",
            'reviewer' => "Code review complete:\n- Code quality: Good\n- Security: No issues\n- Performance: Optimized",
            default => "Task completed successfully for role: {$role}",
        };
    }
}
