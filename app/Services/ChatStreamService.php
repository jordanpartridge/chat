<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ModelName;
use App\Jobs\GenerateChatTitle;
use App\Models\Artifact;
use App\Models\Chat;
use App\Models\Message;
use App\Tools\ConduitKnowledgeTool;
use App\Tools\CreateArtifactTool;
use App\Tools\GenerateLaravelModelTool;
use Generator;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\StreamEventType;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Throwable;

class ChatStreamService
{
    /**
     * @var array<string>
     */
    private const ARTIFACT_TRIGGERS = [
        'create', 'build', 'generate', 'make me', 'show me', 'draw',
        'diagram', 'chart', 'graph', 'dashboard', 'component',
        'visualization', 'visualize', 'flowchart', 'interactive',
        'calculator', 'form', 'svg', 'mermaid', 'react', 'vue',
    ];

    /**
     * @var array<string>
     */
    private const LARAVEL_TRIGGERS = [
        'laravel model', 'eloquent model', 'create model', 'generate model',
        'make model', 'migration', 'eloquent', 'factory', 'seeder', 'database table',
    ];

    /**
     * @var array<string>
     */
    private const KNOWLEDGE_TRIGGERS = [
        'what do you know about', 'do you remember', 'knowledge',
        'what did we discuss', 'previous conversation', 'look up',
        'search for', 'find information', 'recall', 'context about',
    ];

    private string $text = '';

    private ?Message $assistantMessage = null;

    /**
     * @var array<string>
     */
    private array $artifactIds = [];

    /**
     * Stream a chat response.
     *
     * @return Generator<int, string, mixed, void>
     */
    public function stream(Chat $chat, string $userMessage, ModelName $model): Generator
    {
        $this->reset();

        try {
            $this->assistantMessage = $chat->messages()->create([
                'role' => 'assistant',
                'parts' => ['text' => ''],
            ]);

            $tools = $this->buildTools($userMessage, $this->assistantMessage->id);
            $messages = $this->buildConversationHistory($chat);

            $prismBuilder = Prism::text()
                ->using($model->getProvider(), $model->value)
                ->withSystemPrompt($this->buildSystemPrompt(count($tools) > 0))
                ->withMessages($messages);

            if (count($tools) > 0) {
                $prismBuilder = $prismBuilder->withTools($tools)->withMaxSteps(3);
            }

            yield from $this->processStream($prismBuilder->asStream());

            $this->finalizeMessage($chat);

        } catch (Throwable $e) {
            Log::error("Chat stream error for chat {$chat->id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            $this->cleanupOnError();

            yield $this->formatEvent('error', 'An error occurred while streaming the response.');
        }
    }

    private function reset(): void
    {
        $this->text = '';
        $this->assistantMessage = null;
        $this->artifactIds = [];
    }

    /**
     * @return array<object>
     */
    private function buildTools(string $userMessage, string $messageId): array
    {
        $tools = [];

        if ($this->matchesTriggers($userMessage, self::ARTIFACT_TRIGGERS)) {
            $artifactTool = app(CreateArtifactTool::class);
            $artifactTool->setMessageId($messageId);
            $tools[] = $artifactTool;
        }

        if ($this->matchesTriggers($userMessage, self::LARAVEL_TRIGGERS)) {
            $tools[] = app(GenerateLaravelModelTool::class);
        }

        if ($this->matchesTriggers($userMessage, self::KNOWLEDGE_TRIGGERS)) {
            $tools[] = app(ConduitKnowledgeTool::class);
        }

        return $tools;
    }

    /**
     * @param  array<string>  $triggers
     */
    private function matchesTriggers(string $message, array $triggers): bool
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
     * @param  iterable<object>  $stream
     * @return Generator<int, string, mixed, void>
     */
    private function processStream(iterable $stream): Generator
    {
        foreach ($stream as $event) {
            yield from $this->handleStreamEvent($event);
        }
    }

    /**
     * @return Generator<int, string, mixed, void>
     */
    private function handleStreamEvent(object $event): Generator
    {
        match ($event->type()) {
            StreamEventType::TextDelta => $this->text .= $event->delta,
            StreamEventType::ToolResult => $this->captureArtifactId($event->toolResult->result),
            default => null,
        };

        if ($event->type() === StreamEventType::TextDelta) {
            yield $this->formatEvent('text', $event->delta);
        }

        if ($event->type() === StreamEventType::ToolResult) {
            yield from $this->handleToolResult($event);
        }
    }

    private function captureArtifactId(string $result): void
    {
        if (preg_match('/\[artifact:([a-f0-9-]+)\]/', $result, $matches)) {
            $this->artifactIds[] = $matches[1];
        }
    }

    /**
     * @return Generator<int, string, mixed, void>
     */
    private function handleToolResult(object $event): Generator
    {
        $result = $event->toolResult->result;
        $toolName = $event->toolResult->toolName ?? '';

        // Laravel model tool
        if ($toolName === 'generate_laravel_model' && ! str_starts_with($result, 'Error:')) {
            $content = "\n\n".$result;
            $this->text .= $content;
            yield $this->formatEvent('text', $content);
        }

        // Knowledge search tool
        if ($toolName === 'search_knowledge' && preg_match('/\[knowledge:(\d+) results\]/', $result)) {
            $contextStart = strpos($result, "\n\n");
            if ($contextStart !== false) {
                $content = "\n\n**Knowledge Base Results:**".substr($result, $contextStart);
                $this->text .= $content;
                yield $this->formatEvent('text', $content);
            }
        }

        // Artifact creation
        if (preg_match('/\[artifact:([a-f0-9-]+)\]/', $result, $matches)) {
            $artifact = Artifact::find($matches[1]);
            if ($artifact !== null) {
                yield $this->formatArtifactEvent($artifact);
            }
        }
    }

    private function formatEvent(string $type, string $content): string
    {
        return json_encode(['type' => $type, 'content' => $content])."\n";
    }

    private function formatArtifactEvent(Artifact $artifact): string
    {
        return json_encode([
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

    private function finalizeMessage(Chat $chat): void
    {
        if ($this->text !== '' || count($this->artifactIds) > 0) {
            $this->assistantMessage?->update([
                'parts' => ['text' => $this->text],
            ]);
            $chat->touch();

            $messageCount = $chat->messages()->count();
            if ($messageCount === 2 || $messageCount % 10 === 0) {
                GenerateChatTitle::dispatchSync($chat);
            }
        } else {
            $this->assistantMessage?->delete();
        }
    }

    private function cleanupOnError(): void
    {
        if ($this->assistantMessage !== null && $this->text === '') {
            Artifact::where('message_id', $this->assistantMessage->id)->delete();
            $this->assistantMessage->delete();
        }
    }

    private function buildSystemPrompt(bool $toolsEnabled): string
    {
        $basePrompt = 'You are a helpful AI assistant. Answer questions directly and conversationally.';

        if (! $toolsEnabled) {
            return $basePrompt;
        }

        return $basePrompt.<<<'PROMPT'


IMPORTANT - TOOL USAGE RULES:
- You may have access to specialized tools:
  * create_artifact - for visual content (diagrams, components, etc.)
  * generate_laravel_model - for Laravel code scaffolding
  * search_knowledge - for searching the Conduit knowledge base
- ONLY use tools that are available. Do not invent tools or call tools that don't exist.
- Do not invent tools. Do not call "eval", "calculate", or anything else.
- If no tool is appropriate, just respond with text.
- NEVER generate URLs or links. The UI will display results automatically.
- When using search_knowledge, incorporate the retrieved knowledge into your response naturally.
PROMPT;
    }
}
