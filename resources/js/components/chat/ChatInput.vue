<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Send, Loader2 } from 'lucide-vue-next';

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
    <div class="border-t bg-background p-4">
        <form @submit.prevent="handleSubmit" class="flex gap-2">
            <Textarea
                v-model="message"
                name="message"
                placeholder="Type your message..."
                class="min-h-[60px] flex-1 resize-none"
                :disabled="disabled || loading"
                data-test="message-input"
                @keydown="handleKeydown"
            />
            <Button
                type="submit"
                size="icon"
                class="h-[60px] w-[60px]"
                :disabled="!message.trim() || disabled || loading"
                data-test="send-message-button"
            >
                <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
                <Send v-else class="h-4 w-4" />
            </Button>
        </form>
    </div>
</template>
