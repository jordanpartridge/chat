<?php

namespace App\Console\Commands;

use App\Services\AgentStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;
use function Laravel\Prompts\table;
use function Laravel\Prompts\spin;

class AgentSwarmCommand extends Command
{
    protected $signature = 'agent:swarm
        {--kit= : Agent kit to deploy (github-solver, quick-fix, code-review)}
        {--issue= : GitHub issue URL or number to work on}
        {--parallel=4 : Max parallel agents}
        {--dry-run : Show what would be deployed without running}';

    protected $description = 'Deploy an agent swarm to work on a task';

    public function __construct(
        protected AgentStatusService $statusService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Select kit if not provided
        $kit = $this->option('kit') ?? select(
            label: 'Which agent kit do you want to deploy?',
            options: [
                'github-solver' => 'GitHub Issue Solver (architect + implementer + tester + reviewer)',
                'quick-fix' => 'Quick Fix (single implementer)',
                'code-review' => 'Code Review (security + performance + style reviewers)',
                'custom' => 'Custom swarm configuration',
            ],
            default: 'github-solver',
        );

        $agents = $this->getAgentsForKit($kit);

        if ($this->option('dry-run')) {
            $this->showDryRun($kit, $agents);
            return self::SUCCESS;
        }

        // Generate swarm ID
        $swarmId = $this->generateSnowflakeId();

        info("Deploying swarm: {$swarmId}");
        info("Kit: {$kit}");
        info("Agents: " . count($agents));

        // Show what we're deploying
        table(
            ['Agent', 'Role', 'Queue', 'Status'],
            collect($agents)->map(fn ($a) => [$a['name'], $a['role'], $a['queue'], 'Pending'])->toArray()
        );

        if (!confirm('Deploy this swarm?', true)) {
            error('Aborted.');
            return self::FAILURE;
        }

        // Deploy each agent
        foreach ($agents as $agent) {
            $agentId = $this->generateSnowflakeId();

            $this->deployAgent($swarmId, $agentId, $agent);

            info("✓ {$agent['name']} deployed ({$agentId})");
        }

        info('');
        info("Swarm deployed! Monitor with:");
        info("  php artisan agent:watch --swarm={$swarmId}");

        return self::SUCCESS;
    }

    protected function getAgentsForKit(string $kit): array
    {
        return match ($kit) {
            'github-solver' => [
                ['name' => 'Architect', 'role' => 'architect', 'queue' => 'agent:architect', 'prompt' => 'architect.md'],
                ['name' => 'Implementer', 'role' => 'implementer', 'queue' => 'agent:implementer', 'prompt' => 'implementer.md'],
                ['name' => 'Tester', 'role' => 'tester', 'queue' => 'agent:tester', 'prompt' => 'tester.md'],
                ['name' => 'Reviewer', 'role' => 'reviewer', 'queue' => 'agent:reviewer', 'prompt' => 'reviewer.md'],
            ],
            'quick-fix' => [
                ['name' => 'Implementer', 'role' => 'implementer', 'queue' => 'agent:implementer', 'prompt' => 'implementer.md'],
            ],
            'code-review' => [
                ['name' => 'Security Reviewer', 'role' => 'security', 'queue' => 'agent:reviewer', 'prompt' => 'security-reviewer.md'],
                ['name' => 'Performance Reviewer', 'role' => 'performance', 'queue' => 'agent:reviewer', 'prompt' => 'performance-reviewer.md'],
                ['name' => 'Style Reviewer', 'role' => 'style', 'queue' => 'agent:reviewer', 'prompt' => 'style-reviewer.md'],
            ],
            default => [],
        };
    }

    protected function deployAgent(string $swarmId, string $agentId, array $agent): void
    {
        // Create initial status using the service
        $this->statusService->create($agentId, $swarmId, $agent);

        // Update to 'starting' status
        $this->statusService->setStatus($agentId, 'starting');

        // Option 1: Dispatch as queue job
        // AgentTask::dispatch($swarmId, $agentId, $agent)->onQueue($agent['queue']);

        // Option 2: Run as background process (for Claude Code agents)
        $this->runBackgroundAgent($swarmId, $agentId, $agent);
    }

    protected function runBackgroundAgent(string $swarmId, string $agentId, array $agent): void
    {
        // This could exec claude-code CLI or dispatch to Docker container
        // For now, we'll use a wrapper script

        $command = sprintf(
            'php artisan agent:run --swarm=%s --agent=%s --role=%s > /dev/null 2>&1 &',
            $swarmId,
            $agentId,
            $agent['role']
        );

        exec($command);
    }

    protected function generateSnowflakeId(): string
    {
        // Simple snowflake-like ID (timestamp + random)
        // In production, use godruoyi/snowflake
        return (string) (intval(microtime(true) * 1000) . random_int(1000, 9999));
    }

    protected function showDryRun(string $kit, array $agents): void
    {
        info("[DRY RUN] Would deploy:");
        info("Kit: {$kit}");

        table(
            ['Agent', 'Role', 'Queue', 'Prompt'],
            collect($agents)->map(fn ($a) => [$a['name'], $a['role'], $a['queue'], $a['prompt']])->toArray()
        );
    }
}
