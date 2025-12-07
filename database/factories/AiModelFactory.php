<?php

namespace Database\Factories;

use App\Models\AiModel;
use App\Models\UserApiCredential;
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
        return [
            'user_api_credential_id' => UserApiCredential::factory(),
            'name' => fake()->words(2, true),
            'model_id' => fake()->slug(2),
            'context_window' => fake()->randomElement([4096, 8192, 16384, 32768, 128000]),
            'supports_tools' => fake()->boolean(),
            'supports_vision' => fake()->boolean(),
            'speed_tier' => fake()->randomElement(['fast', 'medium', 'slow']),
            'cost_tier' => fake()->randomElement(['low', 'medium', 'high']),
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    public function forCredential(UserApiCredential $credential): static
    {
        return $this->state(fn (array $attributes) => [
            'user_api_credential_id' => $credential->id,
        ]);
    }
}
