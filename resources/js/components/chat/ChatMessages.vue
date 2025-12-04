<script setup lang="ts">
import { ref, watch, nextTick, computed } from 'vue';
import { Sparkles, MessageCircle } from 'lucide-vue-next';
import type { Message, Artifact } from '@/types/chat';
import ChatMessage from './ChatMessage.vue';

const props = defineProps<{
    messages: Message[];
    isStreaming?: boolean;
}>();

defineEmits<{
    'select-artifact': [artifact: Artifact];
}>();

const messagesContainer = ref<HTMLElement | null>(null);

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
};

watch(
    () => props.messages,
    () => scrollToBottom(),
    { deep: true }
);

// Group messages by date
interface MessageGroup {
    date: string;
    label: string;
    messages: Array<{ message: Message; index: number }>;
}

const groupedMessages = computed<MessageGroup[]>(() => {
    const groups: Map<string, MessageGroup> = new Map();
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    props.messages.forEach((message, index) => {
        const messageDate = message.created_at ? new Date(message.created_at) : new Date();
        const dateKey = messageDate.toDateString();

        let label: string;
        if (dateKey === today.toDateString()) {
            label = 'Today';
        } else if (dateKey === yesterday.toDateString()) {
            label = 'Yesterday';
        } else {
            label = messageDate.toLocaleDateString(undefined, {
                weekday: 'long',
                month: 'short',
                day: 'numeric',
            });
        }

        if (!groups.has(dateKey)) {
            groups.set(dateKey, { date: dateKey, label, messages: [] });
        }
        groups.get(dateKey)!.messages.push({ message, index });
    });

    return Array.from(groups.values());
});
</script>

<template>
    <div ref="messagesContainer" class="flex-1 overflow-y-auto aurora-bg">
        <!-- Empty State -->
        <div v-if="messages.length === 0" class="flex h-full items-center justify-center p-8">
            <div class="glass-dark rounded-3xl p-8 text-center shadow-xl max-w-md">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 shadow-lg glow-purple">
                    <MessageCircle class="h-8 w-8 text-white" />
                </div>
                <h3 class="text-xl font-semibold mb-2 text-white">Start a conversation</h3>
                <p class="text-gray-400">
                    Send a message to begin chatting with the AI. Ask questions, get help with code, or just have a conversation.
                </p>
            </div>
        </div>

        <!-- Messages grouped by date -->
        <div v-else class="py-4 relative">
            <template v-for="(group, groupIndex) in groupedMessages" :key="group.date">
                <!-- Date Separator -->
                <div class="date-separator px-4 md:px-6 sticky top-0 z-10 backdrop-blur-sm">
                    <span>{{ group.label }}</span>
                </div>

                <!-- Messages in this group with thread line -->
                <div class="relative">
                    <ChatMessage
                        v-for="({ message, index }, msgIndex) in group.messages"
                        :key="message.id ?? index"
                        :message="message"
                        :class="{ 'thread-line': msgIndex < group.messages.length - 1 }"
                        @select-artifact="$emit('select-artifact', $event)"
                    />
                </div>
            </template>

            <!-- Thinking Indicator with particle animation -->
            <div v-if="isStreaming" class="message-enter px-4 py-4 md:px-6 flex justify-start">
                <div class="flex max-w-[85%] gap-3 md:max-w-[75%]">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full glass-dark glow-indigo">
                        <Sparkles class="h-4 w-4 text-indigo-400" />
                    </div>
                    <div class="glass-assistant rounded-2xl rounded-tl-sm px-5 py-4 shadow-md">
                        <div class="thinking-particles">
                            <span class="thinking-particle"></span>
                            <span class="thinking-particle"></span>
                            <span class="thinking-particle"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
