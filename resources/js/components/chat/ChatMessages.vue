<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';
import type { Message, Artifact } from '@/types/chat';
import ChatMessage from './ChatMessage.vue';

const props = defineProps<{
    messages: Message[];
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
</script>

<template>
    <div ref="messagesContainer" class="flex-1 overflow-y-auto">
        <div v-if="messages.length === 0" class="flex h-full items-center justify-center">
            <div class="text-center text-muted-foreground">
                <p class="text-lg font-medium">Start a conversation</p>
                <p class="text-sm">Send a message to begin chatting with the AI.</p>
            </div>
        </div>
        <div v-else class="divide-y">
            <ChatMessage
                v-for="(message, index) in messages"
                :key="message.id ?? index"
                :message="message"
                @select-artifact="$emit('select-artifact', $event)"
            />
        </div>
    </div>
</template>
