<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Providers\AnthropicProvider;
use App\Services\Providers\XaiProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderValidationController extends Controller
{
    public function __construct(
        private readonly AnthropicProvider $anthropicProvider,
        private readonly XaiProvider $xaiProvider,
    ) {}

    /**
     * Validate an API key and return available models.
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => ['required', 'string', 'in:anthropic,openai,xai,gemini,mistral,groq'],
            'api_key' => ['required', 'string', 'min:10'],
        ]);

        $provider = $request->input('provider');
        $apiKey = $request->input('api_key');

        $result = match ($provider) {
            'anthropic' => $this->anthropicProvider->validateAndFetchModels($apiKey),
            'xai' => $this->xaiProvider->validateAndFetchModels($apiKey),
            default => [
                'valid' => false,
                'models' => [],
                'error' => 'Provider not yet supported for validation',
            ],
        };

        return response()->json($result);
    }
}
