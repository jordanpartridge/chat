<script setup lang="ts">
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import ChatMessages from '@/components/chat/ChatMessages.vue';
import ChatInput from '@/components/chat/ChatInput.vue';
import { useMessageStream } from '@/composables/useMessageStream';
import type { Chat, Message, Model } from '@/types/chat';
import { Role } from '@/types/chat';
import type { BreadcrumbItem } from '@/types';
import { index, show } from '@/actions/App/Http/Controllers/ChatController';

const props = defineProps<{
    chat: Chat;
    chats: Chat[];
    models: Model[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Chats', href: index.url() },
    { title: props.chat.title, href: show.url(props.chat.id) },
]);

const messages = ref<Message[]>(props.chat.messages ?? []);

const { send, isStreaming } = useMessageStream(props.chat.id, messages, () => {
    router.reload({ only: ['chat'] });
});

const handleSubmit = async (message: string) => {
    messages.value.push({
        role: Role.USER,
        parts: { text: message },
    });

    await send({
        message,
        model: props.chat.model,
    });
};
</script>

<template>
    <Head :title="chat.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-col">
            <div class="flex items-center justify-between border-b px-4 py-3">
                <div>
                    <h1 class="font-medium">{{ chat.title }}</h1>
                    <p class="text-sm text-muted-foreground">{{ chat.model }}</p>
                </div>
            </div>

            <ChatMessages :messages="messages" class="flex-1" />

            <ChatInput
                :loading="isStreaming"
                @submit="handleSubmit"
            />
        </div>
    </AppLayout>
</template>
