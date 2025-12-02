<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { X, Copy, Download, ExternalLink, Loader2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import type { Artifact } from '@/types/chat';
import { ArtifactType } from '@/types/chat';
import { render, show } from '@/actions/App/Http/Controllers/ArtifactController';

const props = defineProps<{
    artifact: Artifact | null;
}>();

defineEmits<{
    close: [];
}>();

const fullArtifact = ref<Artifact | null>(null);
const isLoading = ref(false);

// Fetch full artifact data when artifact changes
watch(
    () => props.artifact,
    async (newArtifact) => {
        if (!newArtifact) {
            fullArtifact.value = null;
            return;
        }

        // If we already have content, use it directly
        if (newArtifact.content) {
            fullArtifact.value = newArtifact;
            return;
        }

        // Otherwise fetch the full artifact
        isLoading.value = true;
        try {
            const response = await fetch(show.url(newArtifact.id));
            if (response.ok) {
                fullArtifact.value = await response.json();
            }
        } catch (error) {
            console.error('Failed to load artifact:', error);
        } finally {
            isLoading.value = false;
        }
    },
    { immediate: true }
);

const renderUrl = computed(() => {
    if (!props.artifact) return '';
    return render.url(props.artifact.id);
});

const typeLabel = computed(() => {
    if (!props.artifact) return '';
    switch (props.artifact.type) {
        case ArtifactType.REACT:
            return 'React Component';
        case ArtifactType.VUE:
            return 'Vue Component';
        case ArtifactType.HTML:
            return 'HTML Document';
        case ArtifactType.SVG:
            return 'SVG Graphic';
        case ArtifactType.MERMAID:
            return 'Mermaid Diagram';
        case ArtifactType.MARKDOWN:
            return 'Markdown Document';
        case ArtifactType.CODE:
            return props.artifact.language ?? 'Code';
        default:
            return 'Artifact';
    }
});

const copyToClipboard = async () => {
    if (!fullArtifact.value?.content) return;
    try {
        await navigator.clipboard.writeText(fullArtifact.value.content);
    } catch (error) {
        console.error('Failed to copy:', error);
    }
};

const downloadArtifact = () => {
    if (!fullArtifact.value?.content || !props.artifact) return;

    const extension = getFileExtension(props.artifact);
    const filename = `${props.artifact.title.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.${extension}`;

    const blob = new Blob([fullArtifact.value.content], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
};

const getFileExtension = (artifact: Artifact): string => {
    switch (artifact.type) {
        case ArtifactType.REACT:
            return 'jsx';
        case ArtifactType.VUE:
            return 'vue';
        case ArtifactType.HTML:
            return 'html';
        case ArtifactType.SVG:
            return 'svg';
        case ArtifactType.MERMAID:
            return 'mmd';
        case ArtifactType.MARKDOWN:
            return 'md';
        default:
            return artifact.language ?? 'txt';
    }
};

const openInNewTab = () => {
    if (!props.artifact) return;
    window.open(renderUrl.value, '_blank');
};
</script>

<template>
    <aside
        v-if="artifact"
        class="flex h-full w-full flex-col border-l bg-background md:w-[500px] lg:w-[600px]"
    >
        <header class="flex items-center justify-between border-b px-4 py-3">
            <div class="min-w-0 flex-1">
                <h2 class="truncate font-medium">{{ artifact.title }}</h2>
                <p class="text-xs text-muted-foreground">{{ typeLabel }}</p>
            </div>
            <div class="flex items-center gap-1">
                <Button
                    variant="ghost"
                    size="icon"
                    title="Copy to clipboard"
                    :disabled="isLoading || !fullArtifact?.content"
                    @click="copyToClipboard"
                >
                    <Copy class="h-4 w-4" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    title="Download"
                    :disabled="isLoading || !fullArtifact?.content"
                    @click="downloadArtifact"
                >
                    <Download class="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="icon" title="Open in new tab" @click="openInNewTab">
                    <ExternalLink class="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="icon" title="Close" @click="$emit('close')">
                    <X class="h-4 w-4" />
                </Button>
            </div>
        </header>
        <div class="flex-1 overflow-hidden">
            <iframe
                :src="renderUrl"
                class="h-full w-full border-0"
                sandbox="allow-scripts"
                title="Artifact preview"
            />
        </div>
    </aside>
</template>
