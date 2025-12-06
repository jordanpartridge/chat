<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ChatStreamRequest;
use App\Models\AiModel;
use App\Models\Chat;
use App\Services\ChatStreamService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatStreamController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ChatStreamService $streamService
    ) {}

    public function __invoke(ChatStreamRequest $request, Chat $chat): StreamedResponse
    {
        $this->authorize('stream', $chat);

        $userMessage = $request->string('message')->trim()->value();

        // Get model from request or fall back to chat's model
        $aiModelId = $request->integer('ai_model_id') ?: $chat->ai_model_id;
        $model = AiModel::findOrFail($aiModelId);

        // Update chat's model if changed
        if ($aiModelId !== $chat->ai_model_id) {
            $chat->update(['ai_model_id' => $aiModelId]);
        }

        $chat->messages()->create([
            'role' => 'user',
            'parts' => ['text' => $userMessage],
        ]);

        return Response::stream(function () use ($chat, $userMessage, $model): void {
            foreach ($this->streamService->stream($chat, $userMessage, $model) as $chunk) {
                echo $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
