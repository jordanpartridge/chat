<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentSwarmMember extends Model
{
    protected $fillable = [
        'swarm_id',
        'agent_id',
        'name',
        'role',
        'status',
        'progress',
        'current_task',
        'output',
        'error',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'output' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function swarm(): BelongsTo
    {
        return $this->belongsTo(AgentSwarm::class, 'swarm_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
