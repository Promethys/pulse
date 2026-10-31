<script setup lang="ts">
import { Head, router, useHttp } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, KeyRound, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import AiKeyForm from '@/components/pulse/AiKeyForm.vue';
import EmptyState from '@/components/pulse/EmptyState.vue';
import ProviderLogo from '@/components/pulse/ProviderLogo.vue';
import TestButton from '@/components/pulse/TestButton.vue';
import TestResult from '@/components/pulse/TestResult.vue';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { AiKeyInput, KeyTestResult } from '@/types/onboarding';
import type { AiKeysProps } from '@/types/settings';

const props = defineProps<AiKeysProps>();
const adding = ref(false);
const deleting = ref<number | null>(null);
const testResults = ref<Record<number, KeyTestResult | null>>({});
const errors = ref<Record<string, string>>({});
const processing = ref(false);
const input = ref<AiKeyInput>(emptyInput());
const http = useHttp<Record<string, never>, KeyTestResult>({});
const replacing = computed(() =>
    props.keys.some((key) => key.provider === input.value.provider),
);
const deletionMessage = computed(() => {
    if (props.keys.length !== 1)
        return 'Pulse will stop using this key for your digests.';
    return props.uses_instance_ai
        ? 'Pulse will use its own AI key for future digests.'
        : 'Deleting your last AI key pauses scheduled digests until you add another.';
});
const canAdd = computed(
    () =>
        !processing.value &&
        input.value.api_key.trim() &&
        input.value.model.trim() &&
        input.value.consent,
);
function emptyInput(): AiKeyInput {
    return {
        provider: props.providers[0]?.value ?? '',
        model: props.providers[0]?.suggested_models[0] ?? '',
        api_key: '',
        consent: false,
    };
}
function openAdd() {
    const provider =
        props.providers.find(
            (provider) =>
                !props.keys.some((key) => key.provider === provider.value),
        ) ?? props.providers[0];
    input.value = {
        ...emptyInput(),
        provider: provider?.value ?? '',
        model: provider?.suggested_models[0] ?? '',
    };
    errors.value = {};
    adding.value = true;
}
function move(index: number, direction: number) {
    const ids = props.keys.map((key) => key.id);
    const target = index + direction;
    if (target < 0 || target >= ids.length) return;
    [ids[index], ids[target]] = [ids[target], ids[index]];
    router.put('/settings/ai-keys/order', { ids }, { preserveScroll: true });
}
async function testKey(id: number): Promise<KeyTestResult> {
    return await http.submit({
        method: 'post',
        url: `/settings/ai-keys/${id}/test`,
    });
}
function save() {
    if (!canAdd.value) return;
    processing.value = true;
    router.post(
        '/settings/ai-keys',
        { ...input.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                adding.value = false;
                input.value = emptyInput();
            },
            onError: (value) => {
                errors.value = value;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
function remove() {
    if (deleting.value === null) return;
    router.delete(`/settings/ai-keys/${deleting.value}`, {
        preserveScroll: true,
    });
    deleting.value = null;
}
</script>

<template>
    <Head title="AI keys settings" />
    <Card class="mb-4 shadow-none">
        <CardHeader
            ><CardTitle class="font-display">AI keys</CardTitle
            ><CardDescription
                >Pulse uses the first key and falls back down the list if it
                fails.</CardDescription
            ></CardHeader
        >
        <CardContent class="space-y-4">
            <p
                v-if="uses_instance_ai_notice"
                class="text-sm text-muted-foreground"
            >
                Pulse's own key is used for your digests.
            </p>
            <EmptyState
                v-else-if="!keys.length"
                title="No AI key yet"
                description="Your digest won't run until you add one."
                ><template #icon
                    ><KeyRound class="size-5" aria-hidden="true" /></template
            ></EmptyState>
            <ul
                v-if="keys.length"
                class="divide-y divide-border rounded-xl border border-border"
            >
                <li
                    v-for="(key, index) in keys"
                    :key="key.id"
                    class="flex flex-wrap items-center gap-3 p-3"
                >
                    <div class="flex flex-col">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-6"
                            :disabled="index === 0"
                            aria-label="Move up"
                            @click="move(index, -1)"
                            ><ArrowUp aria-hidden="true"
                        /></Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-6"
                            :disabled="index === keys.length - 1"
                            aria-label="Move down"
                            @click="move(index, 1)"
                            ><ArrowDown aria-hidden="true"
                        /></Button>
                    </div>
                    <ProviderLogo :provider="key.provider" class="size-5" />
                    <div class="min-w-0 flex-1">
                        <p
                            class="flex items-center gap-2 text-sm font-semibold"
                        >
                            {{
                                providers.find(
                                    (provider) =>
                                        provider.value === key.provider,
                                )?.label ?? key.provider
                            }}<Badge v-if="index === 0">Default</Badge>
                        </p>
                        <p
                            class="flex flex-wrap items-center gap-2 font-mono text-xs text-muted-foreground"
                        >
                            <span class="min-w-0 wrap-anywhere">{{
                                key.model
                            }}</span
                            ><span>•••• {{ key.key_suffix }}</span>
                        </p>
                    </div>
                    <div
                        class="flex w-full items-center justify-end gap-2 sm:w-auto"
                    >
                        <TestButton
                            v-model:result="testResults[key.id]"
                            hide-result
                            label="Test"
                            :reset-key="key.key_suffix"
                            :on-test="() => testKey(key.id)"
                        />
                        <Button
                            variant="ghost"
                            class="text-destructive hover:bg-destructive/10 hover:text-destructive dark:hover:bg-destructive/20"
                            @click="deleting = key.id"
                            ><Trash2 aria-hidden="true" />Delete</Button
                        >
                    </div>
                    <TestResult
                        :result="testResults[key.id]"
                        class="w-full text-right"
                    />
                </li>
            </ul>
            <p class="text-sm text-muted-foreground">
                Up to five AI keys, one per provider.
            </p>
            <Button variant="outline" @click="openAdd"
                ><Plus aria-hidden="true" />Add a key</Button
            >
        </CardContent>
    </Card>
    <Dialog v-model:open="adding" @update:open="input.api_key = ''">
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader
                ><DialogTitle class="font-display">{{
                    replacing ? 'Replace key' : 'Add an AI key'
                }}</DialogTitle
                ><DialogDescription
                    >Stored encrypted, used only for your
                    digests.</DialogDescription
                ></DialogHeader
            >
            <form class="space-y-4" @submit.prevent="save">
                <p class="text-sm text-muted-foreground">
                    You can save one key per provider. Adding another key for
                    this provider replaces its saved key and model.
                </p>
                <AiKeyForm
                    v-model="input"
                    :providers="providers"
                    :explainer="false"
                    test-url="/settings/ai-keys/test"
                />
                <InputError
                    v-for="(error, field) in errors"
                    :key="field"
                    :message="error"
                />
                <Button :disabled="!canAdd">{{
                    replacing ? 'Replace key' : 'Add'
                }}</Button>
            </form>
        </DialogContent>
    </Dialog>
    <AlertDialog
        :open="deleting !== null"
        @update:open="!$event && (deleting = null)"
    >
        <AlertDialogContent>
            <AlertDialogTitle class="font-display text-lg font-semibold"
                >Delete AI key?</AlertDialogTitle
            >
            <AlertDialogDescription class="text-sm text-muted-foreground">{{
                deletionMessage
            }}</AlertDialogDescription>
            <div class="flex justify-end gap-2">
                <AlertDialogCancel as-child
                    ><Button variant="outline"
                        >Cancel</Button
                    ></AlertDialogCancel
                >
                <Button
                    variant="ghost"
                    class="text-destructive hover:bg-destructive/10 hover:text-destructive dark:hover:bg-destructive/20"
                    @click="remove"
                    >Delete</Button
                >
            </div>
        </AlertDialogContent>
    </AlertDialog>
</template>
