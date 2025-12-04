<script setup lang="ts">
import { ref } from 'vue';
import { Send, Loader2, Sparkles } from 'lucide-vue-next';

const props = defineProps<{
    disabled?: boolean;
    loading?: boolean;
}>();

const emit = defineEmits<{
    submit: [message: string];
}>();

const message = ref('');

const handleSubmit = () => {
    const trimmed = message.value.trim();
    if (trimmed && !props.disabled && !props.loading) {
        emit('submit', trimmed);
        message.value = '';
    }
};

const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSubmit();
    }
};
</script>

<template>
    <div class="p-4 md:p-6 relative z-10">
        <form
            @submit.prevent="handleSubmit"
            class="glass-dark input-glow relative mx-auto flex max-w-4xl items-end gap-3 rounded-2xl p-3 shadow-2xl transition-all duration-300"
        >
            <!-- Decorative glow -->
            <div class="absolute inset-0 rounded-2xl bg-gradient-to-r from-indigo-500/10 via-purple-500/10 to-pink-500/10 opacity-0 transition-opacity duration-300 group-focus-within:opacity-100 pointer-events-none"></div>

            <div class="flex-1 relative">
                <textarea
                    v-model="message"
                    name="message"
                    placeholder="Type your message..."
                    rows="1"
                    class="w-full resize-none border-0 bg-transparent px-3 py-2.5 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-0"
                    :class="{ 'opacity-50': disabled || loading }"
                    :disabled="disabled || loading"
                    data-test="message-input"
                    @keydown="handleKeydown"
                    @input="($event.target as HTMLTextAreaElement).style.height = 'auto'; ($event.target as HTMLTextAreaElement).style.height = Math.min(($event.target as HTMLTextAreaElement).scrollHeight, 120) + 'px'"
                />
            </div>

            <button
                type="submit"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition-all duration-300"
                :class="
                    message.trim() && !disabled && !loading
                        ? 'bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg glow-purple hover:scale-105'
                        : 'bg-gray-800 text-gray-500 cursor-not-allowed'
                "
                :disabled="!message.trim() || disabled || loading"
                data-test="send-message-button"
            >
                <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
                <Send v-else class="h-4 w-4" />
            </button>
        </form>
        <p class="mt-3 text-center text-xs text-gray-500">
            Press <kbd class="rounded bg-gray-800 px-1.5 py-0.5 font-mono text-[10px] text-gray-400 border border-gray-700">Enter</kbd> to send,
            <kbd class="rounded bg-gray-800 px-1.5 py-0.5 font-mono text-[10px] text-gray-400 border border-gray-700">Shift + Enter</kbd> for new line
        </p>
    </div>
</template>
