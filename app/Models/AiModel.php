<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Prism\Prism\Enums\Provider;

class AiModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'provider',
        'model_id',
        'context_window',
        'supports_tools',
        'supports_vision',
        'speed_tier',
        'cost_tier',
        'enabled',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'context_window' => 'integer',
            'supports_tools' => 'boolean',
            'supports_vision' => 'boolean',
            'enabled' => 'boolean',
            'is_available' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Agent, $this>
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_model');
    }

    /**
     * @param  Builder<AiModel>  $query
     * @return Builder<AiModel>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * @param  Builder<AiModel>  $query
     * @return Builder<AiModel>
     */
    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * @param  Builder<AiModel>  $query
     * @return Builder<AiModel>
     */
    public function scopeSupportsTools(Builder $query): Builder
    {
        return $query->where('supports_tools', true);
    }

    /**
     * @param  Builder<AiModel>  $query
     * @return Builder<AiModel>
     */
    public function scopeSupportsVision(Builder $query): Builder
    {
        return $query->where('supports_vision', true);
    }

    /**
     * @param  Builder<AiModel>  $query
     * @return Builder<AiModel>
     */
    public function scopeBySpeedTier(Builder $query, string $tier): Builder
    {
        return $query->where('speed_tier', $tier);
    }

    /**
     * Get the Prism Provider enum for this model.
     */
    public function getPrismProvider(): Provider
    {
        return match ($this->provider) {
            'ollama' => Provider::Ollama,
            'groq' => Provider::Groq,
            'openai' => Provider::OpenAI,
            'anthropic' => Provider::Anthropic,
            'xai' => Provider::XAI,
            'gemini' => Provider::Gemini,
            'mistral' => Provider::Mistral,
            default => Provider::Ollama,
        };
    }

    /**
     * Check if this model has tool support enabled.
     */
    public function hasToolSupport(): bool
    {
        return $this->supports_tools;
    }
}
