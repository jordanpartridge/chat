<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { MessageSquare } from 'lucide-vue-next';
import type { Chat, Model } from '@/types/chat';
import type { BreadcrumbItem } from '@/types';
import { ref } from 'vue';
import { index, store, show, destroy } from '@/actions/App/Http/Controllers/ChatController';

const props = defineProps<{
    chats: Chat[];
    models: Model[];
}>();

const selectedModel = ref(props.models[0]?.id ?? '');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Chats', href: index.url() },
];

const startNewChat = () => {
    router.post(store.url(), {
        message: 'New conversation',
        model: selectedModel.value,
    });
};

const deleteChat = (chatId: string) => {
    if (confirm('Are you sure you want to delete this chat?')) {
        router.delete(destroy.url(chatId));
    }
};
</script>

<template>
    <Head title="Chats" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-col">
            <div class="border-b p-4">
                <div class="flex items-center gap-4">
                    <Select v-model="selectedModel">
                        <SelectTrigger class="w-[200px]">
                            <SelectValue placeholder="Select a model" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="model in models"
                                :key="model.id"
                                :value="model.id"
                            >
                                {{ model.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Button @click="startNewChat">Start New Chat</Button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-4">
                <div v-if="chats.length === 0" class="flex h-full items-center justify-center">
                    <div class="text-center text-muted-foreground">
                        <MessageSquare class="mx-auto h-12 w-12 mb-4" />
                        <p class="text-lg font-medium">No chats yet</p>
                        <p class="text-sm">Start a new conversation to get going.</p>
                    </div>
                </div>
                <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="chat in chats"
                        :key="chat.id"
                        class="group relative rounded-lg border p-4 transition-colors hover:bg-muted/50"
                    >
                        <a :href="show.url(chat.id)" class="block">
                            <h3 class="font-medium truncate">{{ chat.title }}</h3>
                            <p class="text-sm text-muted-foreground mt-1">
                                {{ chat.model }}
                            </p>
                            <p class="text-xs text-muted-foreground mt-2">
                                {{ new Date(chat.updated_at).toLocaleDateString() }}
                            </p>
                        </a>
                        <button
                            @click="deleteChat(chat.id)"
                            class="absolute right-2 top-2 hidden rounded p-1 text-destructive hover:bg-destructive/10 group-hover:block"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
