<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ModelName;
use App\Http\Requests\ChatStreamRequest;
use App\Models\Chat;
use App\Services\ChatStreamService;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatStreamController extends Controller
{
    public function __construct(
        private readonly ChatStreamService $streamService
    ) {}

    public function __invoke(ChatStreamRequest $request, Chat $chat): StreamedResponse
    {
        abort_unless($chat->user_id === $request->user()->id, 403);

        $userMessage = $request->string('message')->trim()->value();
        $model = $request->enum('model', ModelName::class) ?? ModelName::from($chat->model);

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
