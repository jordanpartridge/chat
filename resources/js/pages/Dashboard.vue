<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import type { Chat, Model } from '@/types/chat';
import { Head, Link, router } from '@inertiajs/vue3';
import { MessageSquare, Plus } from 'lucide-vue-next';
import { index, store, show } from '@/actions/App/Http/Controllers/ChatController';

const props = defineProps<{
    recentChats: Chat[];
    models: Model[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const startNewChat = () => {
    const defaultModel = props.models[0]?.id ?? 'llama3.2';
    router.post(store.url(), {
        message: 'New conversation',
        model: defaultModel,
    });
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <!-- Quick Action: New Chat -->
                <button
                    @click="startNewChat"
                    class="group flex flex-col items-center justify-center gap-3 rounded-xl border border-sidebar-border/70 p-8 transition-colors hover:bg-muted/50 dark:border-sidebar-border"
                >
                    <div class="rounded-full bg-primary/10 p-4">
                        <Plus class="h-8 w-8 text-primary" />
                    </div>
                    <div class="text-center">
                        <h3 class="font-medium">Start New Chat</h3>
                        <p class="text-sm text-muted-foreground">Begin a conversation with AI</p>
                    </div>
                </button>

                <!-- Recent Chats -->
                <div class="rounded-xl border border-sidebar-border/70 p-6 md:col-span-1 lg:col-span-2 dark:border-sidebar-border">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-semibold">Recent Conversations</h2>
                        <Link :href="index.url()" class="text-sm text-primary hover:underline">
                            View all
                        </Link>
                    </div>

                    <div v-if="recentChats.length === 0" class="flex flex-col items-center justify-center py-8 text-center">
                        <MessageSquare class="mb-3 h-10 w-10 text-muted-foreground/50" />
                        <p class="text-muted-foreground">No conversations yet</p>
                        <p class="text-sm text-muted-foreground">Start a new chat to get going</p>
                    </div>

                    <div v-else class="space-y-2">
                        <Link
                            v-for="chat in recentChats"
                            :key="chat.id"
                            :href="show.url(chat.id)"
                            class="flex items-center gap-3 rounded-lg p-3 transition-colors hover:bg-muted/50"
                        >
                            <MessageSquare class="h-5 w-5 text-muted-foreground" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ chat.title }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ chat.model }} · {{ new Date(chat.updated_at).toLocaleDateString() }}
                                </p>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
