<script setup lang="ts">
import { AlertCircle, CheckCircle2, Mail, Trash2, Webhook } from '@lucide/vue';
import { computed, ref } from 'vue';
import DiscordIcon from '@/components/pulse/DiscordIcon.vue';
import TestButton from '@/components/pulse/TestButton.vue';
import TestResult from '@/components/pulse/TestResult.vue';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';
import type { KeyTestResult } from '@/types/onboarding';
import type { DeliveryDestination } from '@/types/settings';

const props = defineProps<{
    channel: DeliveryDestination;
    title: string;
    subtitle?: string;
    onTest: () => Promise<KeyTestResult>;
}>();
const emit = defineEmits<{ 'update:enabled': [value: boolean]; delete: [] }>();
const confirming = ref(false);
const testResult = ref<KeyTestResult | null>(null);

function confirmDelete() {
    confirming.value = false;
    emit('delete');
}
const last = computed(() => props.channel.latest_delivery);
const deliveredAt = computed(() => {
    const value = last.value?.delivered_at ?? last.value?.updated_at;
    if (!value) return '';
    const date = new Date(value);
    return `${date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', timeZone: 'UTC' })}, ${date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', timeZone: 'UTC' })} UTC`;
});
</script>

<template>
    <div :class="cn('space-y-3 p-4', !channel.enabled && 'bg-muted/30')">
        <div class="flex items-start gap-3">
            <span class="mt-0.5 text-muted-foreground">
                <DiscordIcon
                    v-if="channel.type === 'discord_bot'"
                    class="size-4"
                />
                <Mail
                    v-else-if="channel.type === 'email'"
                    class="size-4"
                    aria-hidden="true"
                />
                <Webhook v-else class="size-4" aria-hidden="true" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold">{{ title }}</p>
                <p
                    v-if="subtitle"
                    class="truncate font-mono text-xs text-muted-foreground"
                >
                    {{ subtitle }}
                </p>
            </div>
            <Switch
                :model-value="channel.enabled"
                :aria-label="`Enable ${title}`"
                @update:model-value="emit('update:enabled', $event)"
            />
        </div>
        <p
            v-if="
                !channel.enabled &&
                last?.status === 'failed' &&
                channel.type !== 'email'
            "
            class="text-sm text-muted-foreground"
        >
            Delivery is paused. Check Pulse’s access to this destination and the
            webhook URL if used, send a test, then turn delivery back on.
        </p>
        <slot />
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div
                v-if="last"
                :class="
                    cn(
                        'text-xs',
                        last.status === 'failed'
                            ? 'text-destructive'
                            : 'text-muted-foreground',
                    )
                "
            >
                <span class="inline-flex items-center gap-1">
                    <CheckCircle2
                        v-if="last.status === 'delivered'"
                        class="size-3.5 text-success"
                        aria-hidden="true"
                    />
                    <AlertCircle v-else class="size-3.5" aria-hidden="true" />
                    {{
                        last.status === 'delivered'
                            ? 'Delivered'
                            : last.status === 'failed'
                              ? 'Failed'
                              : 'Pending'
                    }}
                    {{ deliveredAt }}
                </span>
                <p v-if="last.last_error">{{ last.last_error }}</p>
            </div>
            <span v-else class="text-xs text-muted-foreground"
                >Nothing delivered yet</span
            >
            <div class="flex flex-wrap gap-2">
                <TestButton
                    v-model:result="testResult"
                    hide-result
                    :on-test="onTest"
                    :reset-key="subtitle"
                />
                <Button
                    variant="ghost"
                    class="text-destructive hover:bg-destructive/10 hover:text-destructive dark:hover:bg-destructive/20"
                    @click="confirming = true"
                    ><Trash2 aria-hidden="true" />Delete</Button
                >
            </div>
            <TestResult :result="testResult" class="w-full sm:text-right" />
        </div>
        <AlertDialog v-model:open="confirming">
            <AlertDialogContent>
                <AlertDialogTitle class="font-display text-lg font-semibold"
                    >Delete destination?</AlertDialogTitle
                >
                <AlertDialogDescription class="text-sm text-muted-foreground"
                    >This removes {{ title }} and its delivery history. Your
                    digests stay in the app.</AlertDialogDescription
                >
                <div class="flex justify-end gap-2">
                    <AlertDialogCancel as-child
                        ><Button variant="outline"
                            >Cancel</Button
                        ></AlertDialogCancel
                    >
                    <Button
                        variant="ghost"
                        class="text-destructive hover:bg-destructive/10 hover:text-destructive dark:hover:bg-destructive/20"
                        @click="confirmDelete"
                        >Delete</Button
                    >
                </div>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
