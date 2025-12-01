<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Plus, MessageSquare, Trash2 } from 'lucide-vue-next';
import type { Chat } from '@/types/chat';

defineProps<{
    chats: Chat[];
    currentChatId?: string;
}>();

const emit = defineEmits<{
    newChat: [];
    deleteChat: [chatId: string];
}>();
</script>

<template>
    <div class="flex h-full w-64 flex-col border-r bg-muted/30">
        <div class="p-4">
            <Button class="w-full" @click="emit('newChat')">
                <Plus class="mr-2 h-4 w-4" />
                New Chat
            </Button>
        </div>

        <div class="flex-1 overflow-y-auto px-2">
            <div class="space-y-1">
                <Link
                    v-for="chat in chats"
                    :key="chat.id"
                    :href="route('chats.show', chat.id)"
                    class="group flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors hover:bg-muted"
                    :class="currentChatId === chat.id ? 'bg-muted' : ''"
                >
                    <MessageSquare class="h-4 w-4 shrink-0" />
                    <span class="flex-1 truncate">{{ chat.title }}</span>
                    <button
                        @click.prevent="emit('deleteChat', chat.id)"
                        class="hidden rounded p-1 opacity-0 transition-opacity hover:bg-destructive/10 group-hover:block group-hover:opacity-100"
                    >
                        <Trash2 class="h-3 w-3 text-destructive" />
                    </button>
                </Link>
            </div>
        </div>
    </div>
</template>
