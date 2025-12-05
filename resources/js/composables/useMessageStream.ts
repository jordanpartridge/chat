import type { Ref } from 'vue';
import type { Message, StreamEvent, Artifact } from '@/types/chat';
import { useStream } from '@laravel/stream-vue';
import { nextTick } from 'vue';
import { Role } from '@/types/chat';
import ChatStreamController from '@/actions/App/Http/Controllers/ChatStreamController';

interface StreamParams {
    message: string;
    model: string;
}

interface TextStreamEvent {
    type: 'text';
    content: string;
}

interface ArtifactStreamEvent {
    type: 'artifact';
    artifact: Artifact;
}

interface ErrorStreamEvent {
    type: 'error';
    content: string;
}

type ParsedStreamEvent = TextStreamEvent | ArtifactStreamEvent | ErrorStreamEvent;

export function useMessageStream(
    chatId: string,
    messages: Ref<Message[]>,
    onComplete?: () => void,
    onArtifact?: (artifact: Artifact) => void
) {
    const updateMessageWithText = (content: string): void => {
        let currentMessage = messages.value[messages.value.length - 1];

        if (!currentMessage || currentMessage.role !== Role.ASSISTANT) {
            currentMessage = {
                role: Role.ASSISTANT,
                parts: { text: '' },
                artifacts: [],
            };
            messages.value.push(currentMessage);
        }

        if (!currentMessage.parts.text) {
            currentMessage.parts.text = '';
        }

        currentMessage.parts.text += content;
    };

    const addArtifactToMessage = (artifact: Artifact): void => {
        let currentMessage = messages.value[messages.value.length - 1];

        if (!currentMessage || currentMessage.role !== Role.ASSISTANT) {
            currentMessage = {
                role: Role.ASSISTANT,
                parts: { text: '' },
                artifacts: [],
            };
            messages.value.push(currentMessage);
        }

        if (!currentMessage.artifacts) {
            currentMessage.artifacts = [];
        }

        currentMessage.artifacts.push(artifact);

        if (onArtifact) {
            onArtifact(artifact);
        }
    };

    const parseStreamChunk = (chunk: string): void => {
        const lines = chunk
            .trim()
            .split('\n')
            .filter((line) => line.trim());

        for (const line of lines) {
            try {
                const eventData = JSON.parse(line) as ParsedStreamEvent;

                if (eventData.type === 'text') {
                    updateMessageWithText(eventData.content);
                } else if (eventData.type === 'artifact') {
                    addArtifactToMessage(eventData.artifact);
                } else if (eventData.type === 'error') {
                    console.error('Stream error:', eventData.content);
                }
            } catch (error) {
                console.error('Failed to parse JSON line:', error, 'Line:', line);
            }
        }
    };

    const handleStreamError = (error: Error): void => {
        // Check for CSRF token mismatch (419 error)
        // Reload to get fresh token - this handles session expiry gracefully
        if (error?.message?.includes('419') || error?.message?.includes('CSRF')) {
            console.warn('CSRF token expired, reloading page to refresh...');
            window.location.reload();
            return;
        }

        nextTick(() => {
            messages.value.push({
                role: Role.ASSISTANT,
                parts: {
                    text: 'Sorry, there was an error processing your request. Please try again.',
                },
            });
        });
    };

    const stream = useStream<StreamParams, StreamEvent>(
        ChatStreamController.url(chatId),
        {
            onData: parseStreamChunk,
            onError: handleStreamError,
            onFinish: onComplete,
        }
    );

    return {
        ...stream,
        updateMessageWithText,
        addArtifactToMessage,
    };
}
