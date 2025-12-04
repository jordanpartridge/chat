<script setup lang="ts">
import { computed } from 'vue';
import { FileCode, FileText, Image, GitBranch, Component } from 'lucide-vue-next';
import type { Artifact } from '@/types/chat';
import { ArtifactType } from '@/types/chat';

const props = defineProps<{
    artifact: Artifact;
}>();

defineEmits<{
    select: [artifact: Artifact];
}>();

const icon = computed(() => {
    switch (props.artifact.type) {
        case ArtifactType.CODE:
            return FileCode;
        case ArtifactType.MARKDOWN:
            return FileText;
        case ArtifactType.HTML:
            return FileText;
        case ArtifactType.SVG:
            return Image;
        case ArtifactType.MERMAID:
            return GitBranch;
        case ArtifactType.REACT:
        case ArtifactType.VUE:
            return Component;
        default:
            return FileCode;
    }
});

const typeLabel = computed(() => {
    switch (props.artifact.type) {
        case ArtifactType.CODE:
            return props.artifact.language ?? 'Code';
        case ArtifactType.MARKDOWN:
            return 'Markdown';
        case ArtifactType.HTML:
            return 'HTML';
        case ArtifactType.SVG:
            return 'SVG';
        case ArtifactType.MERMAID:
            return 'Diagram';
        case ArtifactType.REACT:
            return 'React';
        case ArtifactType.VUE:
            return 'Vue';
        default:
            return 'Artifact';
    }
});
</script>

<template>
    <button
        type="button"
        class="group flex w-full max-w-sm items-center gap-3 rounded-lg border bg-card p-3 text-left transition-colors hover:bg-accent"
        @click="$emit('select', artifact)"
    >
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
            <component :is="icon" class="h-5 w-5" />
        </div>
        <div class="min-w-0 flex-1">
            <p class="truncate font-medium text-sm">{{ artifact.title }}</p>
            <p class="text-xs text-muted-foreground">{{ typeLabel }}</p>
        </div>
        <div class="text-muted-foreground group-hover:text-foreground">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </div>
    </button>
</template>
