<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { RadioGroupRoot } from 'reka-ui';
import { computed, ref } from 'vue';
import MaskedSecretInput from '@/components/pulse/MaskedSecretInput.vue';
import ProviderCard from '@/components/pulse/ProviderCard.vue';
import TestButton from '@/components/pulse/TestButton.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { test as testAiKey } from '@/routes/onboarding/ai-key';
import type {
    AiKeyInput,
    KeyTestResult,
    OnboardingProvider,
} from '@/types/onboarding';

const props = withDefaults(
    defineProps<{ providers: OnboardingProvider[]; explainer?: boolean }>(),
    { explainer: true },
);
const model = defineModel<AiKeyInput>({ required: true });
const provider = computed(() =>
    props.providers.find((item) => item.value === model.value.provider),
);
const keyHint = computed(() =>
    provider.value
        ? `Get one at ${new URL(provider.value.key_url).host}`
        : undefined,
);
const otherModel = ref(false);
const selectedModel = computed(() =>
    otherModel.value ? '__other' : model.value.model,
);
const http = useHttp<
    Pick<AiKeyInput, 'provider' | 'model' | 'api_key'>,
    KeyTestResult
>({ provider: '', model: '', api_key: '' });
function selectProvider(value: unknown) {
    if (typeof value !== 'string') return;
    const next = props.providers.find((item) => item.value === value);
    if (!next) return;
    otherModel.value = false;
    model.value = {
        ...model.value,
        provider: next.value,
        model: next.suggested_models[0] ?? '',
    };
}
function selectModel(value: unknown) {
    if (typeof value !== 'string') return;
    otherModel.value = value === '__other';
    model.value = {
        ...model.value,
        model: otherModel.value ? '' : value,
    };
}
function update(fields: Partial<AiKeyInput>) {
    model.value = { ...model.value, ...fields };
}
async function testKey(): Promise<KeyTestResult> {
    Object.assign(http, {
        provider: model.value.provider,
        model: model.value.model,
        api_key: model.value.api_key,
    });
    return await http.submit(testAiKey());
}
</script>

<template>
    <div class="space-y-5">
        <div
            v-if="explainer"
            class="rounded-xl bg-secondary p-4 text-sm leading-relaxed"
        >
            <p class="font-semibold">Why your own key?</p>
            <p class="mt-1 text-muted-foreground">
                Pulse uses your AI provider key to pick and summarise your
                digest. Choose a provider and model, then add your key. You can
                change it later in Settings.
            </p>
            <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1">
                <template v-for="(item, index) in providers" :key="item.value">
                    <span
                        v-if="index > 0"
                        aria-hidden="true"
                        class="text-muted-foreground"
                        >·</span
                    >
                    <a
                        :href="item.key_url"
                        target="_blank"
                        rel="noreferrer"
                        class="inline-flex items-center gap-1 font-medium underline-offset-2 hover:underline"
                    >
                        Get a {{ item.label }} key
                        <ExternalLink class="size-3" aria-hidden="true" />
                    </a>
                </template>
            </p>
        </div>
        <RadioGroupRoot
            :model-value="model.provider"
            aria-label="Provider"
            class="grid gap-2 sm:grid-cols-2"
            @update:model-value="selectProvider"
        >
            <ProviderCard
                v-for="item in providers"
                :key="item.value"
                :provider="item"
                :selected="item.value === model.provider"
            />
        </RadioGroupRoot>
        <div class="space-y-1.5">
            <Label for="model">Model</Label>
            <Select
                :model-value="selectedModel"
                @update:model-value="selectModel"
            >
                <SelectTrigger id="model" class="w-full font-mono text-sm"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="suggestion in provider?.suggested_models ?? []"
                        :key="suggestion"
                        :value="suggestion"
                        class="font-mono text-sm"
                        >{{ suggestion }}</SelectItem
                    >
                    <SelectItem value="__other">Other model</SelectItem>
                </SelectContent>
            </Select>
        </div>
        <div v-if="otherModel" class="space-y-1.5">
            <Label for="other-model">Model name</Label>
            <Input
                id="other-model"
                :model-value="model.model"
                maxlength="100"
                class="font-mono text-sm"
                @update:model-value="update({ model: String($event) })"
            />
        </div>
        <MaskedSecretInput
            id="api-key"
            :label="`${provider?.label ?? 'Provider'} API key`"
            :model-value="model.api_key"
            :hint="keyHint"
            @update:model-value="update({ api_key: $event })"
        />
        <div class="flex items-start gap-2">
            <Checkbox
                id="consent"
                :model-value="model.consent"
                class="mt-0.5"
                @update:model-value="update({ consent: $event === true })"
            />
            <Label for="consent" class="leading-snug font-normal"
                >My key is stored encrypted and used only for my own
                digests.</Label
            >
        </div>
        <TestButton
            label="Test the key"
            :on-test="testKey"
            :disabled="!model.api_key.trim() || !model.model.trim()"
            :reset-key="
                JSON.stringify([model.provider, model.model, model.api_key])
            "
        />
    </div>
</template>
