<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Bot, Plus, Settings, Trash2, Zap } from 'lucide-vue-next';
import type { Agent } from '@/types/chat';
import type { BreadcrumbItem } from '@/types';
import { index, create, show, destroy } from '@/actions/App/Http/Controllers/AgentController';

const props = defineProps<{
    agents: Agent[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Agents', href: index.url() },
];

const deleteAgent = (agent: Agent) => {
    if (confirm(`Are you sure you want to delete "${agent.name}"?`)) {
        router.delete(destroy.url(agent.id));
    }
};
</script>

<template>
    <Head title="Agents" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto py-8 px-4">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-white">Agents</h1>
                    <p class="text-gray-400 mt-1">Create and manage custom AI agents</p>
                </div>
                <Link
                    :href="create.url()"
                    class="flex items-center gap-2 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 px-6 py-3 text-sm font-medium text-white shadow-lg transition-all hover:shadow-xl hover:scale-105"
                >
                    <Plus class="h-5 w-5" />
                    Create Agent
                </Link>
            </div>

            <div v-if="agents.length === 0" class="glass-dark rounded-3xl p-10 text-center">
                <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 shadow-lg">
                    <Bot class="h-10 w-10 text-white" />
                </div>
                <h2 class="text-xl font-bold mb-3 text-white">No agents yet</h2>
                <p class="text-gray-400 mb-6">Create your first custom AI agent to get started.</p>
                <Link
                    :href="create.url()"
                    class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 px-6 py-3 text-sm font-medium text-white shadow-lg transition-all hover:shadow-xl hover:scale-105"
                >
                    <Plus class="h-5 w-5" />
                    Create Agent
                </Link>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="agent in agents"
                    :key="agent.id"
                    class="glass-dark rounded-2xl p-6 transition-all hover:bg-white/5"
                >
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600">
                                <Bot class="h-6 w-6 text-white" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-white">{{ agent.name }}</h3>
                                <div class="flex items-center gap-2 text-xs text-gray-400">
                                    <span v-if="agent.user_id === null" class="px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300">System</span>
                                    <span v-else class="px-2 py-0.5 rounded bg-green-500/20 text-green-300">Custom</span>
                                    <span v-if="!agent.is_active" class="px-2 py-0.5 rounded bg-red-500/20 text-red-300">Inactive</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="text-sm text-gray-400 mb-4 line-clamp-2">{{ agent.description }}</p>

                    <div v-if="agent.tools && agent.tools.length > 0" class="flex flex-wrap gap-1 mb-4">
                        <span
                            v-for="tool in agent.tools.slice(0, 3)"
                            :key="tool"
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-white/5 text-xs text-gray-300"
                        >
                            <Zap class="h-3 w-3 text-indigo-400" />
                            {{ tool }}
                        </span>
                        <span v-if="agent.tools.length > 3" class="px-2 py-1 text-xs text-gray-500">
                            +{{ agent.tools.length - 3 }} more
                        </span>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-white/10">
                        <span v-if="agent.default_model" class="text-xs text-gray-500">
                            {{ agent.default_model.name }}
                        </span>
                        <span v-else class="text-xs text-gray-500">No default model</span>

                        <div class="flex items-center gap-2">
                            <Link
                                :href="show.url(agent.id)"
                                class="p-2 rounded-lg hover:bg-white/10 text-gray-400 hover:text-white transition-colors"
                            >
                                <Settings class="h-4 w-4" />
                            </Link>
                            <button
                                v-if="agent.user_id !== null"
                                @click="deleteAgent(agent)"
                                class="p-2 rounded-lg hover:bg-red-500/20 text-gray-400 hover:text-red-400 transition-colors"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
