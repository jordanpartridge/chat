<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import ChatMessages from '@/components/chat/ChatMessages.vue';
import ChatInput from '@/components/chat/ChatInput.vue';
import ArtifactPanel from '@/components/artifacts/ArtifactPanel.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useMessageStream } from '@/composables/useMessageStream';
import type { Chat, Message, Model, Artifact } from '@/types/chat';
import { Role } from '@/types/chat';
import type { BreadcrumbItem } from '@/types';
import { index, show, update } from '@/actions/App/Http/Controllers/ChatController';
import { Sparkles, Zap } from 'lucide-vue-next';

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
const selectedArtifact = ref<Artifact | null>(null);
const selectedModel = ref(props.chat.model);

const handleArtifactCreated = (artifact: Artifact) => {
    selectedArtifact.value = artifact;
};

// Update model when changed
watch(selectedModel, (newModel) => {
    if (newModel !== props.chat.model) {
        router.patch(update.url(props.chat.id), {
            model: newModel,
        }, {
            preserveScroll: true,
        });
    }
});

const { send, isStreaming } = useMessageStream(
    props.chat.id,
    messages,
    () => {
        router.reload({ only: ['chat'] });
    },
    handleArtifactCreated
);

const handleSubmit = async (message: string) => {
    messages.value.push({
        role: Role.USER,
        parts: { text: message },
    });

    await send({
        message,
        model: selectedModel.value,
    });
};

const handleSelectArtifact = (artifact: Artifact) => {
    selectedArtifact.value = artifact;
};

const handleClosePanel = () => {
    selectedArtifact.value = null;
};

const currentModel = computed(() => {
    return props.models.find(m => m.id === selectedModel.value);
});

const supportsTools = computed(() => {
    return currentModel.value?.supportsTools ?? false;
});
</script>

<template>
    <Head :title="chat.title" />

    <AppLayout :breadcrumbs="breadcrumbs" :chats="chats" :current-chat-id="chat.id">
        <div class="flex h-full aurora-bg">
            <div class="flex flex-1 flex-col" :class="{ 'md:mr-0': !selectedArtifact }">
                <!-- Header -->
                <div class="glass-dark border-b border-white/5 px-4 py-3 md:px-6 relative z-10">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 shadow-lg glow-purple">
                                <Sparkles class="h-5 w-5 text-white" />
                            </div>
                            <div>
                                <h1 class="font-semibold text-white">{{ chat.title }}</h1>
                                <div class="flex items-center gap-2">
                                    <span v-if="supportsTools" class="tool-indicator">
                                        Tools Enabled
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <!-- Status indicator -->
                            <div v-if="isStreaming" class="flex items-center gap-2 text-xs text-indigo-400">
                                <Zap class="h-3 w-3 animate-pulse" />
                                <span>Generating...</span>
                            </div>

                            <!-- Model selector -->
                            <Select v-model="selectedModel" data-test="model-selector">
                                <SelectTrigger class="w-[180px] glass-dark border-white/10 text-white text-sm" data-test="model-selector-trigger">
                                    <SelectValue placeholder="Select model" />
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
                        </div>
                    </div>
                </div>

                <ChatMessages
                    :messages="messages"
                    :is-streaming="isStreaming"
                    class="flex-1"
                    @select-artifact="handleSelectArtifact"
                />

                <ChatInput
                    :loading="isStreaming"
                    @submit="handleSubmit"
                />
            </div>

            <ArtifactPanel
                :artifact="selectedArtifact"
                @close="handleClosePanel"
            />
        </div>
    </AppLayout>
</template>
