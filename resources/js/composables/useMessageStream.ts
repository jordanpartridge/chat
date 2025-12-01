import type { Ref } from 'vue';
import type { Message, StreamEvent } from '@/types/chat';
import { useStream } from '@laravel/stream-vue';
import { nextTick } from 'vue';
import { Role, StreamEventType } from '@/types/chat';
import ChatStreamController from '@/actions/App/Http/Controllers/ChatStreamController';

interface StreamParams {
    message: string;
    model: string;
}

export function useMessageStream(
    chatId: string,
    messages: Ref<Message[]>,
    onComplete?: () => void
) {
    const updateMessageWithEvent = (eventData: StreamEvent): void => {
        let currentMessage = messages.value[messages.value.length - 1];

        if (!currentMessage || currentMessage.role !== Role.ASSISTANT) {
            currentMessage = {
                role: Role.ASSISTANT,
                parts: { text: '' },
            };
            messages.value.push(currentMessage);
        }

        if (!currentMessage.parts.text) {
            currentMessage.parts.text = '';
        }

        currentMessage.parts.text += eventData.content;
    };

    const parseStreamChunk = (chunk: string): void => {
        const lines = chunk
            .trim()
            .split('\n')
            .filter((line) => line.trim());

        for (const line of lines) {
            try {
                const eventData = JSON.parse(line) as StreamEvent;
                if (eventData.type !== StreamEventType.ERROR) {
                    updateMessageWithEvent(eventData);
                }
            } catch (error) {
                console.error('Failed to parse JSON line:', error, 'Line:', line);
            }
        }
    };

    const handleStreamError = (): void => {
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
        updateMessageWithEvent,
    };
}
