<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
import InputError from '@/components/InputError.vue';
import { Form } from '@inertiajs/vue3';
import { Trash2, Plus, Eye, EyeOff } from 'lucide-vue-next';

// Import Wayfinder actions (once backend is ready)
// import { store, destroy, toggle } from '@/actions/App/Http/Controllers/Settings/ProviderCredentialController'

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
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
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
            <Dialog v-model:open="showAddDialog">
                <DialogTrigger as-child>
                    <Button v-if="unconfiguredProviders.length > 0">
                        <Plus class="h-4 w-4 mr-2" />
                        Add Provider
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add API Provider</DialogTitle>
                        <DialogDescription>
                            Enter your API key for a provider. Keys are
                            encrypted before storage.
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        action="/settings/providers"
                        method="post"
                        class="space-y-4"
                        @success="resetForm"
                        #default="{ errors, processing }"
                    >
                        <div class="space-y-2">
                            <Label for="provider">Provider</Label>
                            <Select v-model="selectedProvider" name="provider">
                                <SelectTrigger>
                                    <SelectValue
                                        placeholder="Select a provider"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="provider in unconfiguredProviders"
                                        :key="provider.id"
                                        :value="provider.id"
                                    >
                                        {{ provider.name }} -
                                        {{ provider.description }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.provider" />
                        </div>
                        <div class="space-y-2">
                            <Label for="api_key">API Key</Label>
                            <div class="relative">
                                <Input
                                    id="api_key"
                                    name="api_key"
                                    v-model="apiKey"
                                    :type="showApiKey ? 'text' : 'password'"
                                    placeholder="sk-..."
                                    class="pr-10"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="absolute right-0 top-0 h-full px-3"
                                    @click="showApiKey = !showApiKey"
                                >
                                    <Eye
                                        v-if="!showApiKey"
                                        class="h-4 w-4"
                                    />
                                    <EyeOff v-else class="h-4 w-4" />
                                </Button>
                            </div>
                            <InputError :message="errors.api_key" />
                        </div>
                        <DialogFooter>
                            <Button type="submit" :disabled="processing">
                                {{ processing ? 'Adding...' : 'Add Provider' }}
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </SettingsLayout>
</template>
