<?php

namespace App\Console\Commands;

use App\Services\AgentStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Collection;

class AgentWatchCommand extends Command
{
    protected $signature = 'agent:watch
        {--swarm= : Specific swarm ID to watch}
        {--interval=2 : Polling interval in seconds}
        {--once : Run once and exit (no polling)}';

    protected $description = 'Monitor agent swarm progress in real-time';

    public function __construct(
        protected AgentStatusService $statusService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $swarmFilter = $this->option('swarm');
        $interval = (int) $this->option('interval');
        $once = $this->option('once');

        $this->info('Agent Swarm Monitor');
        $this->info('Press Ctrl+C to exit');
        $this->newLine();

        do {
            $this->clearScreen();
            $this->renderDashboard($swarmFilter);

            if (!$once) {
                sleep($interval);
            }
        } while (!$once && $this->shouldContinue());

        return self::SUCCESS;
    }

    protected function renderDashboard(?string $swarmFilter): void
    {
        $agents = $this->loadAgentStatuses($swarmFilter);

        if ($agents->isEmpty()) {
            $this->warn('No agents found.');
            return;
        }

        // Group by swarm
        $swarms = $agents->groupBy('swarm_id');

        foreach ($swarms as $swarmId => $swarmAgents) {
            $this->renderSwarmHeader($swarmId, $swarmAgents);
            $this->renderAgentTable($swarmAgents);
            $this->newLine();
        }

        $this->renderSummary($agents);
    }

    protected function renderSwarmHeader(string $swarmId, Collection $agents): void
    {
        $completed = $agents->where('status', 'completed')->count();
        $total = $agents->count();
        $percent = $total > 0 ? round(($completed / $total) * 100) : 0;

        $this->info("╔══════════════════════════════════════════════════════════════╗");
        $this->info("║  SWARM: {$swarmId}");
        $this->info("║  Progress: {$completed}/{$total} agents ({$percent}%)");
        $this->info("╚══════════════════════════════════════════════════════════════╝");
    }

    protected function renderAgentTable(Collection $agents): void
    {
        $rows = $agents->map(function ($agent) {
            $progress = $this->renderProgressBar($agent['progress'] ?? 0);
            $status = $this->colorizeStatus($agent['status']);
            $duration = $this->calculateDuration($agent);

            return [
                $agent['name'] ?? 'Unknown',
                $agent['role'] ?? '-',
                $progress,
                $status,
                $duration,
            ];
        })->toArray();

        $this->table(
            ['Agent', 'Role', 'Progress', 'Status', 'Duration'],
            $rows
        );
    }

    protected function renderProgressBar(int $percent, int $width = 20): string
    {
        $filled = (int) round(($percent / 100) * $width);
        $empty = $width - $filled;

        $bar = str_repeat('█', $filled) . str_repeat('░', $empty);

        return "{$bar} {$percent}%";
    }

    protected function colorizeStatus(string $status): string
    {
        return match ($status) {
            'completed' => "<fg=green>✓ {$status}</>",
            'running', 'in_progress' => "<fg=yellow>● {$status}</>",
            'failed', 'error' => "<fg=red>✗ {$status}</>",
            'starting', 'pending' => "<fg=gray>○ {$status}</>",
            default => $status,
        };
    }

    protected function calculateDuration(array $agent): string
    {
        $start = $agent['started_at'] ?? null;
        if (!$start) {
            return '-';
        }

        $end = $agent['completed_at'] ?? now()->toIso8601String();

        $startTime = strtotime($start);
        $endTime = strtotime($end);
        $seconds = $endTime - $startTime;

        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;

        return "{$minutes}m {$secs}s";
    }

    protected function renderSummary(Collection $agents): void
    {
        $total = $agents->count();
        $completed = $agents->where('status', 'completed')->count();
        $running = $agents->whereIn('status', ['running', 'in_progress', 'starting'])->count();
        $failed = $agents->whereIn('status', ['failed', 'error'])->count();
        $pending = $agents->where('status', 'pending')->count();

        $this->line("─────────────────────────────────────────────────────────────────");
        $this->line("  Total: {$total}  |  ✓ Completed: {$completed}  |  ● Running: {$running}  |  ✗ Failed: {$failed}  |  ○ Pending: {$pending}");
        $this->line("  Last updated: " . now()->format('H:i:s'));
    }

    protected function loadAgentStatuses(?string $swarmFilter): Collection
    {
        if ($swarmFilter) {
            return $this->statusService->getBySwarm($swarmFilter)
                ->sortBy('created_at');
        }

        return $this->statusService->getAll()
            ->sortBy('created_at');
    }

    protected function clearScreen(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            system('cls');
        } else {
            system('clear');
        }
    }

    protected function shouldContinue(): bool
    {
        // Check if all agents are done
        $agents = $this->loadAgentStatuses($this->option('swarm'));

        $allDone = $agents->every(fn ($a) => in_array($a['status'], ['completed', 'failed', 'error']));

        return !$allDone;
    }
}
