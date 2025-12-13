<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentSwarm extends Model
{
    protected $fillable = [
        'name',
        'description',
        'user_id',
        'status',
        'total_agents',
        'completed_agents',
        'failed_agents',
        'started_at',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(AgentSwarmMember::class, 'swarm_id');
    }

    public function getProgressPercentageAttribute(): int
    {
        if ($this->total_agents === 0) {
            return 0;
        }

        return (int) (($this->completed_agents / $this->total_agents) * 100);
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
