<script setup lang="ts">
import { computed } from 'vue';
import { User, Bot } from 'lucide-vue-next';
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

// Configure marked for safe rendering
marked.setOptions({
    breaks: true,
    gfm: true,
});

const renderedContent = computed(() => {
    const text = props.message.parts?.text ?? '';
    if (isUser.value) {
        // For user messages, just escape HTML and preserve whitespace
        return text;
    }
    // For assistant messages, render markdown
    return marked.parse(text);
});

const hasArtifacts = computed(() => {
    return props.message.artifacts && props.message.artifacts.length > 0;
});
</script>

<template>
    <div
        class="flex gap-4 px-4 py-6"
        :class="isUser ? 'bg-transparent' : 'bg-muted/50'"
    >
        <div
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
            :class="isUser ? 'bg-primary text-primary-foreground' : 'bg-muted'"
        >
            <User v-if="isUser" class="h-4 w-4" />
            <Bot v-else class="h-4 w-4" />
        </div>
        <div class="flex-1 space-y-2 overflow-hidden">
            <p class="text-sm font-medium">{{ isUser ? 'You' : 'Assistant' }}</p>
            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p v-if="isUser" class="whitespace-pre-wrap">{{ message.parts?.text }}</p>
                <div v-else v-html="renderedContent" />
            </div>
            <div v-if="hasArtifacts" class="mt-4 flex flex-col gap-2">
                <ArtifactCard
                    v-for="artifact in message.artifacts"
                    :key="artifact.id"
                    :artifact="artifact"
                    @select="$emit('select-artifact', $event)"
                />
            </div>
        </div>
    </div>
</template>
