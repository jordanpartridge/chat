<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Plus, Sparkles, Zap } from 'lucide-vue-next';
import type { Chat, Model } from '@/types/chat';
import type { BreadcrumbItem } from '@/types';
import { ref } from 'vue';
import { index, store } from '@/actions/App/Http/Controllers/ChatController';

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
        ai_model_id: selectedModel.value,
    });
};
</script>

<template>
    <Head title="Chats" />

    <AppLayout :breadcrumbs="breadcrumbs" :chats="chats">
        <div class="flex h-full flex-col aurora-bg items-center justify-center">
            <!-- Welcome / Empty State -->
            <div class="glass-dark rounded-3xl p-10 text-center shadow-xl max-w-lg mx-4">
                <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 shadow-lg glow-purple">
                    <Sparkles class="h-10 w-10 text-white" />
                </div>
                <h1 class="text-2xl font-bold mb-3 text-white">Welcome to AI Chat</h1>
                <p class="text-gray-400 mb-8 leading-relaxed">
                    Start a conversation with AI. Select a model and begin chatting.
                    Groq models support tool calling for knowledge search.
                </p>

                <div class="flex flex-col gap-4 items-center">
                    <Select v-model="selectedModel">
                        <SelectTrigger class="w-[240px] glass-dark border-white/10 text-white">
                            <SelectValue placeholder="Select a model" />
                        </SelectTrigger>
                        <SelectContent class="glass-dark border-white/10">
                            <SelectItem
                                v-for="model in models"
                                :key="model.id"
                                :value="model.id"
                                class="text-white hover:bg-white/10"
                            >
                                <span class="flex items-center gap-2">
                                    {{ model.name }}
                                    <Zap v-if="model.supportsTools" class="h-3 w-3 text-indigo-400" />
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <button
                        @click="startNewChat"
                        class="flex items-center gap-2 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 px-8 py-3 text-sm font-medium text-white shadow-lg transition-all hover:shadow-xl hover:scale-105 glow-purple"
                    >
                        <Plus class="h-5 w-5" />
                        Start New Chat
                    </button>
                </div>

                <div class="mt-8 pt-6 border-t border-white/10">
                    <p class="text-xs text-gray-500">
                        {{ chats.length }} conversation{{ chats.length !== 1 ? 's' : '' }} in your history
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
