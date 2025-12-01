<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Chat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Throwable;

class GenerateChatTitle implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Chat $chat
    ) {}

    public function handle(): void
    {
        $messages = $this->chat->messages()
            ->orderByDesc('created_at')
            ->limit(6)
            ->get()
            ->reverse();

        if ($messages->isEmpty()) {
            return;
        }

        $conversationSummary = $messages
            ->map(fn ($msg) => "{$msg->role}: {$msg->parts['text']}")
            ->join("\n");

        try {
            $response = Prism::text()
                ->using(Provider::Ollama, 'llama3.2')
                ->withSystemPrompt('Generate a short, descriptive title (3-6 words) for this conversation. Respond with ONLY the title, no quotes, no explanation.')
                ->withPrompt("Conversation:\n{$conversationSummary}")
                ->generate();

            $title = trim($response->text);

            // Clean up common LLM artifacts
            $title = preg_replace('/^[#*_`\-\s]+/', '', $title); // Remove leading markdown
            $title = preg_replace('/[#*_`\-\s]+$/', '', $title); // Remove trailing markdown
            $title = preg_replace('/^["\']+|["\']+$/', '', $title); // Remove quotes
            $title = trim($title);

            if ($title !== '' && strlen($title) <= 100) {
                $this->chat->update(['title' => $title]);
            }
        } catch (Throwable $e) {
            Log::warning("Failed to generate chat title for chat {$this->chat->id}: {$e->getMessage()}");
        }
    }
}
