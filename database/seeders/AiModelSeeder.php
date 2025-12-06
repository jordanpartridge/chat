<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AiModel;
use Illuminate\Database\Seeder;

class AiModelSeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            // Ollama models (local)
            [
                'name' => 'Llama 3.2',
                'description' => 'Latest Llama model, great for general tasks',
                'provider' => 'ollama',
                'model_id' => 'llama3.2',
                'context_window' => 128000,
                'supports_tools' => false,
                'supports_vision' => false,
                'speed_tier' => 'medium',
                'cost_tier' => 'free',
                'enabled' => true,
                'is_available' => false,
            ],
            [
                'name' => 'Llama 3.1',
                'description' => 'Powerful Llama model with extended context',
                'provider' => 'ollama',
                'model_id' => 'llama3.1',
                'context_window' => 128000,
                'supports_tools' => false,
                'supports_vision' => false,
                'speed_tier' => 'medium',
                'cost_tier' => 'free',
                'enabled' => true,
                'is_available' => false,
            ],
            [
                'name' => 'Mistral',
                'description' => 'Fast and efficient for most tasks',
                'provider' => 'ollama',
                'model_id' => 'mistral',
                'context_window' => 32768,
                'supports_tools' => false,
                'supports_vision' => false,
                'speed_tier' => 'fast',
                'cost_tier' => 'free',
                'enabled' => true,
                'is_available' => false,
            ],
            [
                'name' => 'Code Llama',
                'description' => 'Specialized for code generation',
                'provider' => 'ollama',
                'model_id' => 'codellama',
                'context_window' => 16384,
                'supports_tools' => false,
                'supports_vision' => false,
                'speed_tier' => 'medium',
                'cost_tier' => 'free',
                'enabled' => true,
                'is_available' => false,
            ],
            [
                'name' => 'Phi-3',
                'description' => "Microsoft's compact but capable model",
                'provider' => 'ollama',
                'model_id' => 'phi3',
                'context_window' => 4096,
                'supports_tools' => false,
                'supports_vision' => false,
                'speed_tier' => 'fast',
                'cost_tier' => 'free',
                'enabled' => true,
                'is_available' => false,
            ],
            [
                'name' => 'Qwen 2.5',
                'description' => 'Alibaba model, good tool calling support',
                'provider' => 'ollama',
                'model_id' => 'qwen2.5',
                'context_window' => 32768,
                'supports_tools' => false,
                'supports_vision' => false,
                'speed_tier' => 'medium',
                'cost_tier' => 'free',
                'enabled' => true,
                'is_available' => false,
            ],
            // Groq models (cloud, fast inference)
            [
                'name' => 'Llama 3.3 70B (Groq)',
                'description' => 'Latest Llama 3.3, excellent reasoning',
                'provider' => 'groq',
                'model_id' => 'llama-3.3-70b-versatile',
                'context_window' => 128000,
                'supports_tools' => true,
                'supports_vision' => false,
                'speed_tier' => 'fast',
                'cost_tier' => 'low',
                'enabled' => true,
                'is_available' => false,
            ],
            [
                'name' => 'Llama 3.1 8B (Groq)',
                'description' => 'Ultra-fast cloud inference, good for quick tasks',
                'provider' => 'groq',
                'model_id' => 'llama-3.1-8b-instant',
                'context_window' => 128000,
                'supports_tools' => true,
                'supports_vision' => false,
                'speed_tier' => 'fast',
                'cost_tier' => 'low',
                'enabled' => true,
                'is_available' => false,
            ],
            [
                'name' => 'Llama 4 Scout (Groq)',
                'description' => "Meta's newest Llama 4, multimodal capable",
                'provider' => 'groq',
                'model_id' => 'meta-llama/llama-4-scout-17b-16e-instruct',
                'context_window' => 128000,
                'supports_tools' => true,
                'supports_vision' => true,
                'speed_tier' => 'fast',
                'cost_tier' => 'low',
                'enabled' => true,
                'is_available' => false,
            ],
        ];

        foreach ($models as $model) {
            AiModel::updateOrCreate(
                ['provider' => $model['provider'], 'model_id' => $model['model_id']],
                $model
            );
        }
    }
}
