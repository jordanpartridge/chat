<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ModelName;
use App\Http\Requests\ChatStreamRequest;
use App\Jobs\GenerateChatTitle;
use App\Models\Chat;
use App\Models\Message;
use Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Prism\Prism\Enums\StreamEventType;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatStreamController extends Controller
{
    public function __invoke(ChatStreamRequest $request, Chat $chat): StreamedResponse
    {
        abort_unless($chat->user_id === $request->user()->id, 403);

        $userMessage = $request->string('message')->trim()->value();
        $model = $request->enum('model', ModelName::class) ?? ModelName::from($chat->model);

        $chat->messages()->create([
            'role' => 'user',
            'parts' => ['text' => $userMessage],
        ]);

        $messages = $this->buildConversationHistory($chat);

        return Response::stream(function () use ($chat, $messages, $model): Generator {
            $text = '';

            try {
                $response = Prism::text()
                    ->using($model->getProvider(), $model->value)
                    ->withMessages($messages)
                    ->asStream();

                foreach ($response as $event) {
                    if ($event->type() === StreamEventType::TextDelta) {
                        $text .= $event->delta;

                        yield json_encode([
                            'type' => 'text',
                            'content' => $event->delta,
                        ])."\n";
                    }
                }

                if ($text !== '') {
                    $chat->messages()->create([
                        'role' => 'assistant',
                        'parts' => ['text' => $text],
                    ]);
                    $chat->touch();

                    // Generate title after first exchange or periodically
                    $messageCount = $chat->messages()->count();
                    if ($messageCount === 2 || $messageCount % 10 === 0) {
                        GenerateChatTitle::dispatchSync($chat);
                    }
                }

            } catch (Throwable $e) {
                Log::error("Chat stream error for chat {$chat->id}: ".$e->getMessage());

                yield json_encode([
                    'type' => 'error',
                    'content' => 'An error occurred while streaming the response.',
                ])."\n";
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @return array<UserMessage|AssistantMessage>
     */
    private function buildConversationHistory(Chat $chat): array
    {
        return $chat->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (Message $message): UserMessage|AssistantMessage => match ($message->role) {
                'user' => new UserMessage(content: $message->parts['text'] ?? ''),
                'assistant' => new AssistantMessage(content: $message->parts['text'] ?? ''),
            })
            ->toArray();
    }
}
