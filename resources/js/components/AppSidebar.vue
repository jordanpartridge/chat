<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarGroupContent,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { BookOpen, Folder, Plus, MessageCircle, Sparkles } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { index, store, show } from '@/actions/App/Http/Controllers/ChatController';
import type { Chat } from '@/types/chat';

const props = defineProps<{
    chats?: Chat[];
    currentChatId?: string;
}>();

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];

const startNewChat = () => {
    router.post(store.url(), {
        message: 'New conversation',
        model: props.chats?.[0]?.model ?? 'llama3.2',
    });
};

const isGroqModel = (modelId: string) => {
    return modelId.includes('groq') || modelId.includes('llama-3.') || modelId.includes('meta-llama');
};
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-r-0">
        <SidebarHeader class="border-b border-white/5">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="index.url()" class="flex items-center gap-2">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600">
                                <Sparkles class="h-4 w-4 text-white" />
                            </div>
                            <span class="font-semibold text-white">AI Chat</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="px-2">
            <!-- New Chat Button -->
            <div class="p-2">
                <button
                    @click="startNewChat"
                    class="flex w-full items-center gap-2 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 px-4 py-2.5 text-sm font-medium text-white shadow-lg transition-all hover:shadow-xl hover:scale-[1.02]"
                >
                    <Plus class="h-4 w-4" />
                    <span>New Chat</span>
                </button>
            </div>

            <!-- Chat List -->
            <SidebarGroup v-if="chats && chats.length > 0">
                <SidebarGroupLabel class="text-gray-400 text-xs uppercase tracking-wider px-2">
                    Recent Chats
                </SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="chat in chats" :key="chat.id">
                            <SidebarMenuButton
                                as-child
                                :class="[
                                    'group relative rounded-lg transition-all',
                                    currentChatId === chat.id
                                        ? 'bg-indigo-500/20 text-white'
                                        : 'text-gray-400 hover:bg-white/5 hover:text-white'
                                ]"
                            >
                                <Link :href="show.url(chat.id)" class="flex items-center gap-3 px-3 py-2">
                                    <div
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
                                        :class="isGroqModel(chat.model) ? 'bg-indigo-500/30' : 'bg-gray-700'"
                                    >
                                        <MessageCircle class="h-3.5 w-3.5" />
                                    </div>
                                    <span class="truncate text-sm">{{ chat.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter class="border-t border-white/5">
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

<style scoped>
:deep(.sidebar) {
    --sidebar-background: rgba(10, 10, 15, 0.95);
    --sidebar-foreground: #fff;
    --sidebar-border: rgba(255, 255, 255, 0.05);
}
</style>
