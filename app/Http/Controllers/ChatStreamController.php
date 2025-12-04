<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ModelName;
use App\Http\Requests\ChatStreamRequest;
use App\Jobs\GenerateChatTitle;
use App\Models\Artifact;
use App\Models\Chat;
use App\Models\Message;
use App\Tools\CreateArtifactTool;
use App\Tools\GenerateLaravelModelTool;
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
    /**
     * Trigger words that indicate the user wants an artifact created.
     * Only enable tools when these patterns are detected.
     *
     * @var array<string>
     */
    private const ARTIFACT_TRIGGERS = [
        'create',
        'build',
        'generate',
        'make me',
        'show me',
        'draw',
        'diagram',
        'chart',
        'graph',
        'dashboard',
        'component',
        'visualization',
        'visualize',
        'flowchart',
        'interactive',
        'calculator',
        'form',
        'svg',
        'mermaid',
        'react',
        'vue',
    ];

    /**
     * Trigger words that indicate the user wants Laravel code generation.
     *
     * @var array<string>
     */
    private const LARAVEL_TRIGGERS = [
        'model',
        'migration',
        'eloquent',
        'factory',
        'seeder',
        'laravel',
        'database table',
    ];

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
        $shouldEnableArtifactTools = $this->shouldEnableTriggerWords($userMessage, self::ARTIFACT_TRIGGERS);
        $shouldEnableLaravelTools = $this->shouldEnableTriggerWords($userMessage, self::LARAVEL_TRIGGERS);

        return Response::stream(function () use ($chat, $messages, $model, $shouldEnableArtifactTools, $shouldEnableLaravelTools): Generator {
            $text = '';
            $assistantMessage = null;
            $artifactIds = [];

            try {
                // Create the assistant message first so tools can reference it
                $assistantMessage = $chat->messages()->create([
                    'role' => 'assistant',
                    'parts' => ['text' => ''],
                ]);

                $tools = [];

                // Only enable tools when user explicitly requests specific content
                // Ollama models have poor tool-calling discipline and will call
                // tools for everything if always enabled
                if ($shouldEnableArtifactTools) {
                    $artifactTool = app(CreateArtifactTool::class);
                    $artifactTool->setMessageId($assistantMessage->id);
                    $tools[] = $artifactTool;
                }

                if ($shouldEnableLaravelTools) {
                    $tools[] = app(GenerateLaravelModelTool::class);
                }

                $prismBuilder = Prism::text()
                    ->using($model->getProvider(), $model->value)
                    ->withSystemPrompt($this->getSystemPrompt(count($tools) > 0))
                    ->withMessages($messages);

                if (count($tools) > 0) {
                    $prismBuilder = $prismBuilder->withTools($tools)->withMaxSteps(3);
                }

                $response = $prismBuilder->asStream();

                foreach ($response as $event) {
                    match ($event->type()) {
                        StreamEventType::TextDelta => (function () use (&$text, $event): void {
                            $text .= $event->delta;
                        })(),

                        StreamEventType::ToolCall => (function (): void {
                            // Tool is being called - could emit a "thinking" event here
                        })(),

                        StreamEventType::ToolResult => (function () use (&$artifactIds, $event): void {
                            // Check if an artifact was created
                            $result = $event->toolResult->result;
                            if (preg_match('/\[artifact:([a-f0-9-]+)\]/', $result, $matches)) {
                                $artifactIds[] = $matches[1];
                            }
                        })(),

                        default => null,
                    };

                    // Stream text deltas to the client
                    if ($event->type() === StreamEventType::TextDelta) {
                        yield json_encode([
                            'type' => 'text',
                            'content' => $event->delta,
                        ])."\n";
                    }

                    // Stream tool results to the client
                    if ($event->type() === StreamEventType::ToolResult) {
                        $result = $event->toolResult->result;
                        $toolName = $event->toolResult->toolName ?? '';

                        // Stream Laravel model tool output directly as text
                        if ($toolName === 'generate_laravel_model' && ! str_starts_with($result, 'Error:')) {
                            yield json_encode([
                                'type' => 'text',
                                'content' => "\n\n".$result,
                            ])."\n";
                            $text .= "\n\n".$result;
                        }

                        // Stream artifact events when tools create them
                        if (preg_match('/\[artifact:([a-f0-9-]+)\]/', $result, $matches)) {
                            $artifact = Artifact::find($matches[1]);
                            if ($artifact !== null) {
                                yield json_encode([
                                    'type' => 'artifact',
                                    'artifact' => [
                                        'id' => $artifact->id,
                                        'identifier' => $artifact->identifier,
                                        'type' => $artifact->type,
                                        'title' => $artifact->title,
                                        'language' => $artifact->language,
                                    ],
                                ])."\n";
                            }
                        }
                    }
                }

                // Update the assistant message with the final text
                if ($text !== '' || count($artifactIds) > 0) {
                    $assistantMessage->update([
                        'parts' => ['text' => $text],
                    ]);
                    $chat->touch();

                    // Generate title after first exchange or periodically
                    $messageCount = $chat->messages()->count();
                    if ($messageCount === 2 || $messageCount % 10 === 0) {
                        GenerateChatTitle::dispatchSync($chat);
                    }
                } else {
                    // No content generated, delete the empty message
                    $assistantMessage->delete();
                }

            } catch (Throwable $e) {
                Log::error("Chat stream error for chat {$chat->id}: ".$e->getMessage(), [
                    'exception' => $e,
                ]);

                // Clean up empty message and any orphaned artifacts on error
                if ($assistantMessage !== null && $text === '') {
                    // Delete any artifacts created for this message first
                    Artifact::where('message_id', $assistantMessage->id)->delete();
                    $assistantMessage->delete();
                }

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

    /**
     * Check if the user message contains any of the given trigger words.
     *
     * @param  array<string>  $triggers
     */
    private function shouldEnableTriggerWords(string $message, array $triggers): bool
    {
        $lowercaseMessage = strtolower($message);

        foreach ($triggers as $trigger) {
            if (str_contains($lowercaseMessage, $trigger)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the system prompt for the chat.
     */
    private function getSystemPrompt(bool $toolsEnabled = false): string
    {
        $basePrompt = 'You are a helpful AI assistant. Answer questions directly and conversationally.';

        if ($toolsEnabled) {
            return $basePrompt.<<<'PROMPT'


IMPORTANT - TOOL USAGE RULES:
- You may have access to specialized tools: create_artifact (for visual content) and/or generate_laravel_model (for Laravel code scaffolding).
- ONLY use tools that are available. Do not invent tools or call tools that don't exist.
- Do not invent tools. Do not call "eval", "calculate", "search", or anything else.
- If no tool is appropriate, just respond with text.
- NEVER generate URLs or links. The UI will display results automatically.
- After using a tool, briefly confirm what was created - do not make up URLs.
PROMPT;
        }

        return $basePrompt;
    }
}
