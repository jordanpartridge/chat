<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
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
    <AppShell variant="sidebar" class="dark">
        <AppSidebar :chats="chats" :current-chat-id="currentChatId" />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
    </AppShell>
</template>
