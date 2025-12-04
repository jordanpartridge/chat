<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\OllamaService;
use Prism\Prism\Enums\Provider;

enum ModelName: string
{
    case LLAMA32 = 'llama3.2';
    case LLAMA31 = 'llama3.1';
    case MISTRAL = 'mistral';
    case CODELLAMA = 'codellama';
    case PHI3 = 'phi3';
    case QWEN25 = 'qwen2.5';

    /**
     * Get all defined models (regardless of availability).
     *
     * @return array<array{id: string, name: string, description: string, provider: string}>
     */
    public static function getAllModels(): array
    {
        return array_map(
            fn (ModelName $model): array => $model->toArray(),
            self::cases()
        );
    }

    /**
     * Get only models that are installed in Ollama.
     *
     * @return array<array{id: string, name: string, description: string, provider: string}>
     */
    public static function getAvailableModels(?OllamaService $ollamaService = null): array
    {
        $ollamaService ??= app(OllamaService::class);
        $installedNames = $ollamaService->getInstalledModelNames();

        return array_values(array_filter(
            self::getAllModels(),
            fn (array $model): bool => in_array($model['id'], $installedNames, true)
        ));
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
        };
    }

    public function getProvider(): Provider
    {
        return Provider::Ollama;
    }

    /**
     * @return array{id: string, name: string, description: string, provider: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->value,
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'provider' => $this->getProvider()->value,
        ];
    }
}
