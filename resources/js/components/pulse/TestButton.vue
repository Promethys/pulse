<script setup lang="ts">
import { Check, Loader2, X, Zap } from '@lucide/vue';
import { ref, watch } from 'vue';
import TestResult from '@/components/pulse/TestResult.vue';
import { Button } from '@/components/ui/button';
import type { KeyTestResult } from '@/types/onboarding';

const props = withDefaults(
    defineProps<{
        label?: string;
        onTest: () => Promise<KeyTestResult>;
        disabled?: boolean;
        resetKey?: string;
        hideResult?: boolean;
    }>(),
    { label: 'Send a test' },
);
const loading = ref(false);
const result = defineModel<KeyTestResult | null>('result', { default: null });
let attempt = 0;
watch(
    () => props.resetKey,
    () => {
        attempt++;
        result.value = null;
    },
);
async function run() {
    if (loading.value || props.disabled) return;
    const current = ++attempt;
    loading.value = true;
    result.value = null;
    try {
        const response = await props.onTest();
        if (current === attempt) result.value = response;
    } catch (error) {
        if (current === attempt)
            result.value = {
                ok: false,
                message:
                    error instanceof Error
                        ? error.message
                        : 'Could not test the key. Please try again.',
            };
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="inline-flex flex-col items-start gap-1">
        <Button
            type="button"
            variant="outline"
            :disabled="disabled || loading"
            @click="run"
            class="focus-ring hover:bg-accent hover:text-accent-foreground active:bg-accent active:text-accent-foreground dark:active:bg-accent"
        >
            <Loader2 v-if="loading" class="animate-spin" aria-hidden="true" />
            <Check
                v-else-if="result?.ok"
                class="text-success"
                aria-hidden="true"
            />
            <X v-else-if="result" class="text-destructive" aria-hidden="true" />
            <Zap v-else aria-hidden="true" />
            {{ loading ? 'Testing…' : label }}
        </Button>
        <TestResult v-if="!hideResult" :result="result" />
    </div>
</template>
