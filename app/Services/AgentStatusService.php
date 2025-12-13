<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AgentStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class AgentStatusService
{
    private string $basePath;

    public function __construct()
    {
        $this->basePath = storage_path('agents');

        if (! File::exists($this->basePath)) {
            File::makeDirectory($this->basePath, 0755, true);
        }
    }

    /**
     * Create a new agent status file
     */
    public function create(string $agentId, string $swarmId, array $agent): void
    {
        $data = [
            'agent_id' => $agentId,
            'swarm_id' => $swarmId,
            'agent' => $agent,
            'status' => AgentStatus::Pending->value,
            'progress' => 0,
            'output' => null,
            'error' => null,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->writeStatus($agentId, $data);
    }

    /**
     * Update agent status with arbitrary data
     */
    public function update(string $agentId, array $data): void
    {
        $current = $this->get($agentId);

        if ($current === null) {
            return;
        }

        $updated = array_merge($current, $data, [
            'updated_at' => now()->toIso8601String(),
        ]);

        $this->writeStatus($agentId, $updated);
    }

    /**
     * Get agent status
     */
    public function get(string $agentId): ?array
    {
        $path = $this->getStatusPath($agentId);

        if (! File::exists($path)) {
            return null;
        }

        $content = File::get($path);

        return json_decode($content, true);
    }

    /**
     * Get all agents
     */
    public function getAll(): Collection
    {
        $agents = collect();

        if (! File::exists($this->basePath)) {
            return $agents;
        }

        $files = File::files($this->basePath);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            $content = File::get($file->getPathname());
            $data = json_decode($content, true);

            if ($data) {
                $agents->push($data);
            }
        }

        return $agents;
    }

    /**
     * Get all agents belonging to a swarm
     */
    public function getBySwarm(string $swarmId): Collection
    {
        return $this->getAll()
            ->filter(fn ($agent) => isset($agent['swarm_id']) && $agent['swarm_id'] === $swarmId);
    }

    /**
     * Set agent progress percentage
     */
    public function setProgress(string $agentId, int $percent): void
    {
        $this->update($agentId, [
            'progress' => max(0, min(100, $percent)),
        ]);
    }

    /**
     * Set agent status
     */
    public function setStatus(string $agentId, string $status): void
    {
        $this->update($agentId, [
            'status' => $status,
        ]);
    }

    /**
     * Mark agent as completed
     */
    public function complete(string $agentId, ?string $output = null): void
    {
        $data = [
            'status' => AgentStatus::Completed->value,
            'progress' => 100,
            'completed_at' => now()->toIso8601String(),
        ];

        if ($output !== null) {
            $data['output'] = $output;
        }

        $this->update($agentId, $data);
    }

    /**
     * Mark agent as failed
     */
    public function fail(string $agentId, string $error): void
    {
        $this->update($agentId, [
            'status' => AgentStatus::Failed->value,
            'error' => $error,
            'failed_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Clean up old agent status files
     *
     * @return int Number of files cleaned up
     */
    public function cleanup(int $olderThanMinutes = 60): int
    {
        $count = 0;
        $cutoff = now()->subMinutes($olderThanMinutes);

        if (! File::exists($this->basePath)) {
            return 0;
        }

        $files = File::files($this->basePath);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            $content = File::get($file->getPathname());
            $data = json_decode($content, true);

            if (! isset($data['updated_at'])) {
                continue;
            }

            $updatedAt = \Carbon\Carbon::parse($data['updated_at']);

            if ($updatedAt->lessThan($cutoff)) {
                File::delete($file->getPathname());
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get the file path for an agent status
     */
    private function getStatusPath(string $agentId): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . $agentId . '.json';
    }

    /**
     * Write status data to file
     */
    private function writeStatus(string $agentId, array $data): void
    {
        $path = $this->getStatusPath($agentId);

        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
