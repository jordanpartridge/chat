<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\OllamaService;
use Prism\Prism\Enums\Provider;

enum ModelName: string
{
    // Ollama models (local)
    case LLAMA32 = 'llama3.2';
    case LLAMA31 = 'llama3.1';
    case MISTRAL = 'mistral';
    case CODELLAMA = 'codellama';
    case PHI3 = 'phi3';
    case QWEN25 = 'qwen2.5';

    // Groq models (cloud, fast inference)
    case GROQ_LLAMA33_70B = 'llama-3.3-70b-versatile';
    case GROQ_LLAMA31_8B = 'llama-3.1-8b-instant';
    case GROQ_LLAMA4_SCOUT = 'meta-llama/llama-4-scout-17b-16e-instruct';

    /**
     * Get all defined models (regardless of availability).
     *
     * @return array<array{id: string, name: string, description: string, provider: string, supportsTools: bool}>
     */
    public static function getAllModels(): array
    {
        return array_map(
            fn (ModelName $model): array => $model->toArray(),
            self::cases()
        );
    }

    /**
     * Get available models based on configuration.
     * - Ollama models: only if installed locally
     * - Groq models: only if API key is configured
     *
     * @return array<array{id: string, name: string, description: string, provider: string, supportsTools: bool}>
     */
    public static function getAvailableModels(?OllamaService $ollamaService = null): array
    {
        $available = [];

        // Add Ollama models that are installed
        $ollamaService ??= app(OllamaService::class);
        $installedNames = $ollamaService->getInstalledModelNames();

        foreach (self::cases() as $model) {
            if ($model->getProvider() === Provider::Ollama) {
                if (in_array($model->value, $installedNames, true)) {
                    $available[] = $model->toArray();
                }
            }
        }

        // Add Groq models if API key is configured
        if (config('prism.providers.groq.api_key')) {
            foreach (self::cases() as $model) {
                if ($model->getProvider() === Provider::Groq) {
                    $available[] = $model->toArray();
                }
            }
        }

        return $available;
    }

    public function getName(): string
    {
        return match ($this) {
            self::LLAMA32 => 'Llama 3.2',
            self::LLAMA31 => 'Llama 3.1',
            self::MISTRAL => 'Mistral',
            self::CODELLAMA => 'Code Llama',
            self::PHI3 => 'Phi-3',
            self::QWEN25 => 'Qwen 2.5',
            self::GROQ_LLAMA33_70B => 'Llama 3.3 70B (Groq)',
            self::GROQ_LLAMA31_8B => 'Llama 3.1 8B (Groq)',
            self::GROQ_LLAMA4_SCOUT => 'Llama 4 Scout (Groq)',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::LLAMA32 => 'Latest Llama model, great for general tasks',
            self::LLAMA31 => 'Powerful Llama model with extended context',
            self::MISTRAL => 'Fast and efficient for most tasks',
            self::CODELLAMA => 'Specialized for code generation',
            self::PHI3 => 'Microsoft\'s compact but capable model',
            self::QWEN25 => 'Alibaba model, good tool calling support',
            self::GROQ_LLAMA33_70B => 'Latest Llama 3.3, excellent reasoning',
            self::GROQ_LLAMA31_8B => 'Ultra-fast cloud inference, good for quick tasks',
            self::GROQ_LLAMA4_SCOUT => 'Meta\'s newest Llama 4, multimodal capable',
        };
    }

    public function getProvider(): Provider
    {
        return match ($this) {
            self::LLAMA32, self::LLAMA31, self::MISTRAL,
            self::CODELLAMA, self::PHI3, self::QWEN25 => Provider::Ollama,
            self::GROQ_LLAMA33_70B, self::GROQ_LLAMA31_8B,
            self::GROQ_LLAMA4_SCOUT => Provider::Groq,
        };
    }

    /**
     * Check if this model supports tool calling reliably.
     * Groq models handle tools well, Ollama models are unreliable.
     */
    public function supportsTools(): bool
    {
        return $this->getProvider() === Provider::Groq;
    }

    /**
     * @return array{id: string, name: string, description: string, provider: string, supportsTools: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->value,
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'provider' => $this->getProvider()->value,
            'supportsTools' => $this->supportsTools(),
        ];
    }
}
