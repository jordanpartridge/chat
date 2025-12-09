<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Agent extends Model
{
    /** @use HasFactory<\Database\Factories\AgentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'user_id',
        'default_model_id',
        'system_prompt',
        'avatar',
        'tools',
        'capabilities',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tools' => 'array',
            'capabilities' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<AiModel, $this>
     */
    public function defaultModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'default_model_id');
    }

    /**
     * @return BelongsToMany<AiModel, $this>
     */
    public function models(): BelongsToMany
    {
        return $this->belongsToMany(AiModel::class, 'agent_model');
    }

    /**
     * @param  Builder<Agent>  $query
     * @return Builder<Agent>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Agent>  $query
     * @return Builder<Agent>
     */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    /**
     * @param  Builder<Agent>  $query
     * @return Builder<Agent>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Check if this agent has a specific tool enabled.
     */
    public function hasTool(string $tool): bool
    {
        return in_array($tool, $this->tools ?? [], true);
    }

    /**
     * Check if this agent has a specific capability.
     */
    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }
}
