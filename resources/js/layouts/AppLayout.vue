<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import type { Chat } from '@/types/chat';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    chats?: Chat[];
    currentChatId?: string;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    chats: () => [],
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :chats="chats" :current-chat-id="currentChatId">
        <slot />
    </AppLayout>
</template>
