<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserApiCredential>
 */
class UserApiCredentialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'provider' => fake()->randomElement(['openai', 'anthropic', 'xai', 'gemini', 'mistral', 'groq']),
            'api_key' => 'sk-test-'.fake()->lexify('??????????????????????????'),
            'is_enabled' => true,
        ];
    }

    /**
     * Indicate that the credential is for OpenAI.
     */
    public function openai(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'openai',
            'api_key' => 'sk-test-'.fake()->lexify('??????????????????????????'),
        ]);
    }

    /**
     * Indicate that the credential is for Anthropic.
     */
    public function anthropic(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'anthropic',
            'api_key' => 'sk-ant-'.fake()->lexify('??????????????????????????'),
        ]);
    }

    /**
     * Indicate that the credential is for xAI.
     */
    public function xai(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'xai',
            'api_key' => 'xai-'.fake()->lexify('??????????????????????????'),
        ]);
    }

    /**
     * Indicate that the credential is for Groq.
     */
    public function groq(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'groq',
            'api_key' => 'gsk_'.fake()->lexify('??????????????????????????'),
        ]);
    }

    /**
     * Indicate that the credential is for Gemini.
     */
    public function gemini(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'gemini',
            'api_key' => 'AIzaSy'.fake()->lexify('??????????????????????????'),
        ]);
    }

    /**
     * Indicate that the credential is for Mistral.
     */
    public function mistral(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'mistral',
            'api_key' => 'mistral-'.fake()->lexify('??????????????????????????'),
        ]);
    }
}
