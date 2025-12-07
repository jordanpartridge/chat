<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import { Form } from '@inertiajs/vue3';
import { Trash2, Plus, Eye, EyeOff, Loader2, CheckCircle, XCircle } from 'lucide-vue-next';

interface ProviderModel {
    id: string;
    name: string;
    description: string;
}

interface Credential {
    id: number;
    provider: string;
    is_enabled: boolean;
    last_used_at: string | null;
    created_at: string;
}

interface Provider {
    id: string;
    name: string;
    description: string;
}

const props = defineProps<{
    credentials: Credential[];
    availableProviders: Provider[];
}>();

const showAddDialog = ref(false);
const showApiKey = ref(false);
const selectedProvider = ref('');
const apiKey = ref('');

// Validation state
const isValidating = ref(false);
const validationResult = ref<{ valid: boolean; models: ProviderModel[]; error: string | null } | null>(null);
const selectedModels = ref<string[]>([]);

const providerNames: Record<string, string> = {
    openai: 'OpenAI',
    anthropic: 'Anthropic',
    xai: 'xAI (Grok)',
    gemini: 'Google Gemini',
    mistral: 'Mistral',
    groq: 'Groq',
};

const getProviderName = (provider: string) =>
    providerNames[provider] || provider;

const unconfiguredProviders = computed(() => {
    const configured = props.credentials.map((c) => c.provider);
    return props.availableProviders.filter(
        (p) => !configured.includes(p.id)
    );
});

const resetForm = () => {
    selectedProvider.value = '';
    apiKey.value = '';
    showApiKey.value = false;
    showAddDialog.value = false;
    validationResult.value = null;
    selectedModels.value = [];
    isValidating.value = false;
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
};

// Debounced validation
let validationTimeout: ReturnType<typeof setTimeout> | null = null;

const validateApiKey = async () => {
    if (!selectedProvider.value || apiKey.value.length < 10) {
        validationResult.value = null;
        return;
    }

    isValidating.value = true;
    validationResult.value = null;

    try {
        const response = await fetch('/settings/providers/validate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                provider: selectedProvider.value,
                api_key: apiKey.value,
            }),
        });

        const result = await response.json();
        validationResult.value = result;

        // Auto-select all models by default
        if (result.valid && result.models) {
            selectedModels.value = result.models.map((m: ProviderModel) => m.id);
        }
    } catch {
        validationResult.value = {
            valid: false,
            models: [],
            error: 'Failed to validate API key',
        };
    } finally {
        isValidating.value = false;
    }
};

// Watch for API key changes and debounce validation
watch([apiKey, selectedProvider], () => {
    if (validationTimeout) {
        clearTimeout(validationTimeout);
    }

    validationResult.value = null;

    if (apiKey.value.length >= 10 && selectedProvider.value) {
        validationTimeout = setTimeout(validateApiKey, 500);
    }
});

const toggleModel = (modelId: string) => {
    const index = selectedModels.value.indexOf(modelId);
    if (index === -1) {
        selectedModels.value.push(modelId);
    } else {
        selectedModels.value.splice(index, 1);
    }
};

const isModelSelected = (modelId: string) => selectedModels.value.includes(modelId);

const canSubmit = computed(() => {
    return validationResult.value?.valid && selectedModels.value.length > 0;
});

const submitForm = () => {
    if (!canSubmit.value) return;

    router.post('/settings/providers', {
        provider: selectedProvider.value,
        api_key: apiKey.value,
        models: selectedModels.value,
    }, {
        onSuccess: () => resetForm(),
    });
};
</script>

<template>
    <SettingsLayout>
        <Head title="API Providers" />

        <div class="space-y-6">
            <HeadingSmall
                title="API Providers"
                description="Configure API keys for AI providers. Your keys are encrypted and stored securely."
            />

            <!-- Configured Providers -->
            <div class="space-y-4">
                <div
                    v-if="credentials.length === 0"
                    class="rounded-lg border border-dashed p-8 text-center"
                >
                    <p class="text-muted-foreground">
                        No providers configured yet.
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Add an API key to get started with cloud AI models.
                    </p>
                </div>

                <Card v-for="credential in credentials" :key="credential.id">
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <div>
                            <CardTitle class="text-base">{{
                                getProviderName(credential.provider)
                            }}</CardTitle>
                            <CardDescription>
                                Added {{ formatDate(credential.created_at) }}
                                <span v-if="credential.last_used_at">
                                    · Last used
                                    {{ formatDate(credential.last_used_at) }}
                                </span>
                            </CardDescription>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge
                                :variant="
                                    credential.is_enabled
                                        ? 'default'
                                        : 'secondary'
                                "
                            >
                                {{
                                    credential.is_enabled
                                        ? 'Enabled'
                                        : 'Disabled'
                                }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="flex items-center justify-between">
                        <code class="text-sm text-muted-foreground"
                            >••••••••••••••••</code
                        >
                        <div class="flex gap-2">
                            <Form
                                :action="`/settings/providers/${credential.id}/toggle`"
                                method="post"
                                class="inline"
                            >
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                >
                                    {{
                                        credential.is_enabled
                                            ? 'Disable'
                                            : 'Enable'
                                    }}
                                </Button>
                            </Form>
                            <Dialog>
                                <DialogTrigger as-child>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        :data-testid="`delete-${credential.provider}`"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle
                                            >Remove
                                            {{
                                                getProviderName(
                                                    credential.provider
                                                )
                                            }}?</DialogTitle
                                        >
                                        <DialogDescription>
                                            This will remove your API key. You
                                            can add it again later.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter>
                                        <Form
                                            :action="`/settings/providers/${credential.id}`"
                                            method="delete"
                                        >
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                >Remove Provider</Button
                                            >
                                        </Form>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Add Provider Button -->
            <Dialog v-model:open="showAddDialog" @update:open="(open) => !open && resetForm()">
                <DialogTrigger as-child>
                    <Button v-if="unconfiguredProviders.length > 0">
                        <Plus class="h-4 w-4 mr-2" />
                        Add Provider
                    </Button>
                </DialogTrigger>
                <DialogContent class="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Add API Provider</DialogTitle>
                        <DialogDescription>
                            Enter your API key. We'll validate it and show available models.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4">
                        <!-- Provider Selection -->
                        <div class="space-y-2">
                            <Label for="provider">Provider</Label>
                            <Select v-model="selectedProvider">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a provider" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="provider in unconfiguredProviders"
                                        :key="provider.id"
                                        :value="provider.id"
                                    >
                                        {{ provider.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- API Key Input -->
                        <div class="space-y-2">
                            <Label for="api_key">API Key</Label>
                            <div class="relative">
                                <Input
                                    id="api_key"
                                    v-model="apiKey"
                                    :type="showApiKey ? 'text' : 'password'"
                                    placeholder="sk-..."
                                    class="pr-20"
                                    :disabled="!selectedProvider"
                                />
                                <div class="absolute right-0 top-0 h-full flex items-center pr-2 gap-1">
                                    <!-- Validation Status -->
                                    <div v-if="isValidating" class="text-muted-foreground">
                                        <Loader2 class="h-4 w-4 animate-spin" />
                                    </div>
                                    <div v-else-if="validationResult?.valid" class="text-green-500">
                                        <CheckCircle class="h-4 w-4" />
                                    </div>
                                    <div v-else-if="validationResult && !validationResult.valid" class="text-red-500">
                                        <XCircle class="h-4 w-4" />
                                    </div>

                                    <!-- Toggle visibility -->
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 w-8 p-0"
                                        @click="showApiKey = !showApiKey"
                                    >
                                        <Eye v-if="!showApiKey" class="h-4 w-4" />
                                        <EyeOff v-else class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                            <p v-if="validationResult?.error" class="text-sm text-red-500">
                                {{ validationResult.error }}
                            </p>
                            <p v-else-if="apiKey.length > 0 && apiKey.length < 10" class="text-sm text-muted-foreground">
                                Keep typing...
                            </p>
                        </div>

                        <!-- Model Selection -->
                        <div v-if="validationResult?.valid && validationResult.models.length > 0" class="space-y-3">
                            <Label>Select Models to Enable</Label>
                            <div class="rounded-lg border p-3 space-y-2 max-h-48 overflow-y-auto">
                                <div
                                    v-for="model in validationResult.models"
                                    :key="model.id"
                                    class="flex items-start gap-3 p-2 rounded hover:bg-muted/50 cursor-pointer"
                                    @click="toggleModel(model.id)"
                                >
                                    <Checkbox
                                        :id="model.id"
                                        :checked="isModelSelected(model.id)"
                                        @update:checked="toggleModel(model.id)"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <label :for="model.id" class="text-sm font-medium cursor-pointer">
                                            {{ model.name }}
                                        </label>
                                        <p class="text-xs text-muted-foreground">
                                            {{ model.description }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ selectedModels.length }} of {{ validationResult.models.length }} models selected
                            </p>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            :disabled="!canSubmit"
                            @click="submitForm"
                        >
                            Add Provider
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </SettingsLayout>
</template>
