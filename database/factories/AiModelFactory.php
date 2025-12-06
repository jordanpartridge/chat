<?php

namespace Database\Factories;

use App\Models\AiModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiModel>
 */
class AiModelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $providers = ['ollama', 'groq', 'openai', 'anthropic'];
        $provider = fake()->randomElement($providers);

        return [
            'name' => fake()->words(2, true),
            'provider' => $provider,
            'model_id' => fake()->slug(2),
            'context_window' => fake()->randomElement([4096, 8192, 16384, 32768, 128000]),
            'supports_tools' => fake()->boolean(),
            'supports_vision' => fake()->boolean(),
            'speed_tier' => fake()->randomElement(['fast', 'medium', 'slow']),
            'cost_tier' => fake()->randomElement(['low', 'medium', 'high']),
            'enabled' => true,
            'is_available' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    public function ollama(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'ollama',
            'cost_tier' => 'low',
        ]);
    }

    public function groq(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'groq',
            'speed_tier' => 'fast',
        ]);
    }

    public function openai(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'openai',
        ]);
    }

    public function anthropic(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'anthropic',
        ]);
    }
}
