<script setup lang="ts">
import { computed, ref } from 'vue';
import { Sparkles, Copy, Check } from 'lucide-vue-next';
import { marked } from 'marked';
import type { Message, Artifact } from '@/types/chat';
import { Role } from '@/types/chat';
import ArtifactCard from '@/components/artifacts/ArtifactCard.vue';

const props = defineProps<{
    message: Message;
}>();

defineEmits<{
    'select-artifact': [artifact: Artifact];
}>();

const isUser = computed(() => props.message.role === Role.USER);
const copied = ref(false);

// Configure marked for safe rendering
marked.setOptions({
    breaks: true,
    gfm: true,
});

const renderedContent = computed(() => {
    const text = props.message.parts?.text ?? '';
    if (isUser.value) {
        return text;
    }
    return marked.parse(text);
});

const hasArtifacts = computed(() => {
    return props.message.artifacts && props.message.artifacts.length > 0;
});

const hasToolUsage = computed(() => {
    return props.message.tool_calls && props.message.tool_calls.length > 0;
});

const copyMessage = async () => {
    const text = props.message.parts?.text ?? '';
    await navigator.clipboard.writeText(text);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
};
</script>

<template>
    <div
        class="message-enter message-card px-4 py-4 md:px-6 group"
        :class="isUser ? 'flex justify-end' : 'flex justify-start'"
        :data-testid="isUser ? 'user-message' : 'assistant-message'"
    >
        <div
            class="flex max-w-[85%] gap-3 md:max-w-[75%]"
            :class="isUser ? 'flex-row-reverse' : 'flex-row'"
        >
            <!-- Avatar -->
            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full shadow-lg"
                :class="
                    isUser
                        ? 'bg-gradient-to-br from-indigo-500 to-purple-600 text-white glow-purple'
                        : 'glass-dark'
                "
            >
                <span v-if="isUser" class="text-sm font-semibold">{{ 'JP'.charAt(0) }}</span>
                <Sparkles v-else class="h-4 w-4 text-indigo-400" />
            </div>

            <!-- Message Content -->
            <div class="space-y-2 relative">
                <!-- Tool usage indicator -->
                <div v-if="hasToolUsage && !isUser" class="mb-2">
                    <span class="tool-indicator">
                        Knowledge Search
                    </span>
                </div>

                <div
                    class="rounded-2xl px-4 py-3 shadow-md transition-all duration-200"
                    :class="
                        isUser
                            ? 'glass-user text-white rounded-tr-sm'
                            : 'glass-assistant rounded-tl-sm'
                    "
                >
                    <div
                        class="prose prose-sm max-w-none prose-invert"
                        data-testid="message-content"
                    >
                        <p v-if="isUser" class="whitespace-pre-wrap m-0 text-white" data-testid="plain-text-content">{{ message.parts?.text }}</p>
                        <div v-else v-html="renderedContent" class="[&>p:first-child]:mt-0 [&>p:last-child]:mb-0 text-gray-100 [&_p]:text-gray-100 [&_li]:text-gray-100 [&_strong]:text-white [&_code]:text-indigo-300" data-testid="markdown-content" />
                    </div>
                </div>

                <!-- Hover Actions -->
                <div
                    v-if="!isUser"
                    class="message-actions absolute -bottom-6 left-0 flex items-center gap-1"
                >
                    <button
                        @click="copyMessage"
                        class="flex items-center gap-1.5 px-2 py-1 text-xs rounded-lg glass-dark text-gray-400 hover:text-white transition-colors"
                    >
                        <Check v-if="copied" class="h-3 w-3 text-green-400" />
                        <Copy v-else class="h-3 w-3" />
                        <span>{{ copied ? 'Copied' : 'Copy' }}</span>
                    </button>
                </div>

                <!-- Artifacts -->
                <div v-if="hasArtifacts" class="flex flex-col gap-2 mt-3">
                    <ArtifactCard
                        v-for="artifact in message.artifacts"
                        :key="artifact.id"
                        :artifact="artifact"
                        class="glass-dark rounded-xl border border-indigo-500/20"
                        @select="$emit('select-artifact', $event)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
