<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chat>
 */
class ChatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'ai_model_id' => AiModel::factory(),
        ];
    }

    /**
     * Use a specific AI model.
     */
    public function withModel(AiModel $model): static
    {
        return $this->state(fn (array $attributes) => [
            'ai_model_id' => $model->id,
        ]);
    }
}
