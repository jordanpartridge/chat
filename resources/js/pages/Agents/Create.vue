<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Form, Head } from '@inertiajs/vue3';
import { Bot } from 'lucide-vue-next';
import type { Model, ToolOption, CapabilityOption } from '@/types/chat';
import type { BreadcrumbItem } from '@/types';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { index, create, store } from '@/actions/App/Http/Controllers/AgentController';
import { ref } from 'vue';

const props = defineProps<{
    models: Model[];
    availableTools: ToolOption[];
    availableCapabilities: CapabilityOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Agents', href: index.url() },
    { title: 'Create', href: create.url() },
];

const selectedModel = ref<string>('');
const selectedTools = ref<string[]>([]);
const selectedCapabilities = ref<string[]>([]);

const toggleTool = (toolId: string) => {
    const index = selectedTools.value.indexOf(toolId);
    if (index === -1) {
        selectedTools.value.push(toolId);
    } else {
        selectedTools.value.splice(index, 1);
    }
};

const toggleCapability = (capId: string) => {
    const index = selectedCapabilities.value.indexOf(capId);
    if (index === -1) {
        selectedCapabilities.value.push(capId);
    } else {
        selectedCapabilities.value.splice(index, 1);
    }
};
</script>

<template>
    <Head title="Create Agent" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto py-8 px-4 max-w-3xl">
            <div class="flex items-center gap-4 mb-8">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 shadow-lg">
                    <Bot class="h-7 w-7 text-white" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-white">Create Agent</h1>
                    <p class="text-gray-400">Define a custom AI agent with specific behaviors</p>
                </div>
            </div>

            <Form
                v-bind="store.form()"
                class="space-y-8"
                v-slot="{ errors, processing }"
            >
                <div class="glass-dark rounded-2xl p-6 space-y-6">
                    <HeadingSmall
                        title="Basic Information"
                        description="Give your agent a name and description"
                    />

                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                placeholder="e.g., Research Assistant"
                                class="bg-white/5 border-white/10"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                name="description"
                                required
                                rows="3"
                                placeholder="Describe what this agent does..."
                                class="flex min-h-[80px] w-full rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            ></textarea>
                            <InputError :message="errors.description" />
                        </div>
                    </div>
                </div>

                <div class="glass-dark rounded-2xl p-6 space-y-6">
                    <HeadingSmall
                        title="System Prompt"
                        description="Instructions that define the agent's behavior"
                    />

                    <div class="grid gap-2">
                        <Label for="system_prompt">System Prompt</Label>
                        <textarea
                            id="system_prompt"
                            name="system_prompt"
                            rows="6"
                            placeholder="You are a helpful assistant that..."
                            class="flex min-h-[120px] w-full rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"
                        ></textarea>
                        <InputError :message="errors.system_prompt" />
                    </div>
                </div>

                <div class="glass-dark rounded-2xl p-6 space-y-6">
                    <HeadingSmall
                        title="Model Configuration"
                        description="Select the default AI model for this agent"
                    />

                    <div class="grid gap-2">
                        <Label>Default Model</Label>
                        <Select v-model="selectedModel" name="default_model_id">
                            <SelectTrigger class="bg-white/5 border-white/10">
                                <SelectValue placeholder="Select a model" />
                            </SelectTrigger>
                            <SelectContent class="glass-dark border-white/10">
                                <SelectItem
                                    v-for="model in models"
                                    :key="model.id"
                                    :value="model.id"
                                    class="text-white hover:bg-white/10"
                                >
                                    {{ model.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input type="hidden" name="default_model_id" :value="selectedModel" />
                    </div>
                </div>

                <div class="glass-dark rounded-2xl p-6 space-y-6">
                    <HeadingSmall
                        title="Tools"
                        description="Enable tools for this agent to use"
                    />

                    <div class="grid gap-3 md:grid-cols-2">
                        <label
                            v-for="tool in availableTools"
                            :key="tool.id"
                            class="flex items-start gap-3 p-4 rounded-xl border border-white/10 hover:bg-white/5 cursor-pointer transition-colors"
                            :class="{ 'border-indigo-500 bg-indigo-500/10': selectedTools.includes(tool.id) }"
                        >
                            <Checkbox
                                :checked="selectedTools.includes(tool.id)"
                                @update:checked="toggleTool(tool.id)"
                            />
                            <input
                                v-if="selectedTools.includes(tool.id)"
                                type="hidden"
                                name="tools[]"
                                :value="tool.id"
                            />
                            <div>
                                <div class="font-medium text-white">{{ tool.name }}</div>
                                <div class="text-sm text-gray-400">{{ tool.description }}</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="glass-dark rounded-2xl p-6 space-y-6">
                    <HeadingSmall
                        title="Capabilities"
                        description="Define what this agent excels at"
                    />

                    <div class="grid gap-3 md:grid-cols-2">
                        <label
                            v-for="cap in availableCapabilities"
                            :key="cap.id"
                            class="flex items-start gap-3 p-4 rounded-xl border border-white/10 hover:bg-white/5 cursor-pointer transition-colors"
                            :class="{ 'border-purple-500 bg-purple-500/10': selectedCapabilities.includes(cap.id) }"
                        >
                            <Checkbox
                                :checked="selectedCapabilities.includes(cap.id)"
                                @update:checked="toggleCapability(cap.id)"
                            />
                            <input
                                v-if="selectedCapabilities.includes(cap.id)"
                                type="hidden"
                                name="capabilities[]"
                                :value="cap.id"
                            />
                            <div>
                                <div class="font-medium text-white">{{ cap.name }}</div>
                                <div class="text-sm text-gray-400">{{ cap.description }}</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-4">
                    <Button
                        type="submit"
                        :disabled="processing"
                        class="bg-gradient-to-br from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700"
                    >
                        {{ processing ? 'Creating...' : 'Create Agent' }}
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
