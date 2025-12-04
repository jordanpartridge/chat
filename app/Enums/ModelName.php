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
     * Determine which models are available at runtime based on installed providers and configuration.
     *
     * Ollama-backed models are included only when they are installed locally; Groq-backed models are included only when a Groq API key is configured.
     *
     * @return array<array{id: string, name: string, description: string, provider: string, supportsTools: bool}> Array of model descriptors with their metadata.
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

    /**
     * Get the human-friendly display name for this model.
     *
     * @return string The display name for the model (for example, "Llama 3.2" or "Llama 3.3 70B (Groq)").
     */
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

    / **
     * Provide a short human-readable description for the model.
     *
     * @return string A brief human-readable description of the enum model case.
     */
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

    /**
     * Get the provider associated with this model.
     *
     * Maps each enum case to its corresponding Provider (e.g., Ollama or Groq).
     *
     * @return Provider The provider enum value for this model.
     */
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
     * Determine whether the model reliably supports tool calling.
     *
     * @return bool `true` if the model reliably supports tool calling (Groq models), `false` otherwise.
     */
    public function supportsTools(): bool
    {
        return $this->getProvider() === Provider::Groq;
    }

    /**
     * Convert this model enum case to an associative representation.
     *
     * @return array{id: string, name: string, description: string, provider: string, supportsTools: bool} Associative array with keys: `id` — model identifier, `name` — human-friendly model name, `description` — model description, `provider` — provider identifier, `supportsTools` — `true` if the model reliably supports tool calls, `false` otherwise.
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