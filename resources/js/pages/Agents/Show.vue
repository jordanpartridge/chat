<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Bot, Edit, Trash2, Zap, Brain, MessageSquare } from 'lucide-vue-next';
import type { Agent, Model, ToolOption, CapabilityOption } from '@/types/chat';
import type { BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { index, show, edit, destroy } from '@/actions/App/Http/Controllers/AgentController';

const props = defineProps<{
    agent: Agent;
    models: Model[];
    availableTools: ToolOption[];
    availableCapabilities: CapabilityOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Agents', href: index.url() },
    { title: props.agent.name, href: show.url(props.agent.id) },
];

const deleteAgent = () => {
    if (confirm(`Are you sure you want to delete "${props.agent.name}"?`)) {
        router.delete(destroy.url(props.agent.id));
    }
};

const getToolName = (toolId: string) => {
    return props.availableTools.find(t => t.id === toolId)?.name ?? toolId;
};

const getCapabilityName = (capId: string) => {
    return props.availableCapabilities.find(c => c.id === capId)?.name ?? capId;
};
</script>

<template>
    <Head :title="agent.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto py-8 px-4 max-w-4xl">
            <div class="glass-dark rounded-3xl p-8 mb-6">
                <div class="flex items-start justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 shadow-lg">
                            <Bot class="h-8 w-8 text-white" />
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-white">{{ agent.name }}</h1>
                            <div class="flex items-center gap-2 mt-1">
                                <span v-if="agent.user_id === null" class="px-2 py-0.5 rounded text-xs bg-indigo-500/20 text-indigo-300">System Agent</span>
                                <span v-else class="px-2 py-0.5 rounded text-xs bg-green-500/20 text-green-300">Custom Agent</span>
                                <span v-if="agent.is_active" class="px-2 py-0.5 rounded text-xs bg-green-500/20 text-green-300">Active</span>
                                <span v-else class="px-2 py-0.5 rounded text-xs bg-red-500/20 text-red-300">Inactive</span>
                            </div>
                        </div>
                    </div>
                    <div v-if="agent.user_id !== null" class="flex items-center gap-2">
                        <Link :href="edit.url(agent.id)">
                            <Button variant="outline" size="sm" class="gap-2">
                                <Edit class="h-4 w-4" />
                                Edit
                            </Button>
                        </Link>
                        <Button variant="destructive" size="sm" class="gap-2" @click="deleteAgent">
                            <Trash2 class="h-4 w-4" />
                            Delete
                        </Button>
                    </div>
                </div>

                <p class="text-gray-300 mb-6">{{ agent.description }}</p>

                <div v-if="agent.default_model" class="flex items-center gap-2 text-sm text-gray-400">
                    <MessageSquare class="h-4 w-4" />
                    Default Model: <span class="text-white">{{ agent.default_model.name }}</span>
                </div>
            </div>

            <div v-if="agent.system_prompt" class="glass-dark rounded-2xl p-6 mb-6">
                <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                    <Brain class="h-5 w-5 text-purple-400" />
                    System Prompt
                </h2>
                <pre class="text-sm text-gray-300 whitespace-pre-wrap font-mono bg-black/20 rounded-xl p-4 max-h-64 overflow-auto">{{ agent.system_prompt }}</pre>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="glass-dark rounded-2xl p-6">
                    <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                        <Zap class="h-5 w-5 text-indigo-400" />
                        Tools
                    </h2>
                    <div v-if="agent.tools && agent.tools.length > 0" class="space-y-2">
                        <div
                            v-for="tool in agent.tools"
                            :key="tool"
                            class="px-3 py-2 rounded-lg bg-white/5 text-sm text-gray-300"
                        >
                            {{ getToolName(tool) }}
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500">No tools enabled</p>
                </div>

                <div class="glass-dark rounded-2xl p-6">
                    <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                        <Brain class="h-5 w-5 text-purple-400" />
                        Capabilities
                    </h2>
                    <div v-if="agent.capabilities && agent.capabilities.length > 0" class="space-y-2">
                        <div
                            v-for="cap in agent.capabilities"
                            :key="cap"
                            class="px-3 py-2 rounded-lg bg-white/5 text-sm text-gray-300"
                        >
                            {{ getCapabilityName(cap) }}
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500">No capabilities defined</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
