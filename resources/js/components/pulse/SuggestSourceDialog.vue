<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { suggest } from '@/actions/App/Http/Controllers/SourcesController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { ManagedSource, OnboardingInterest } from '@/types';

const props = defineProps<{
    open: boolean;
    source: ManagedSource | null;
    interests: OnboardingInterest[];
}>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();
const form = useForm({ interest: '', reason: '' });
const liveInterests = computed(() =>
    props.interests.filter((interest) => interest.status === 'live'),
);
const canSubmit = computed(
    () =>
        !form.processing &&
        liveInterests.value.some(
            (interest) => interest.slug === form.interest,
        ) &&
        form.reason.length >= 10 &&
        form.reason.length <= 500,
);
watch(
    () => [props.open, props.source] as const,
    () => {
        if (!props.open) return;
        form.reset();
        form.clearErrors();
        const first = props.source?.interests[0];
        form.interest = liveInterests.value.some(
            (interest) => interest.slug === first?.slug,
        )
            ? first!.slug
            : '';
    },
    { immediate: true },
);
function submit() {
    if (!canSubmit.value || !props.source) return;
    form.post(suggest.url(props.source.id), {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Suggest for the catalog</DialogTitle>
                <DialogDescription
                    >Share a feed you enjoy. We will review it before adding it
                    to the catalog.</DialogDescription
                >
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <Label for="suggest-source-interest">Interest</Label>
                    <Select v-model="form.interest">
                        <SelectTrigger
                            id="suggest-source-interest"
                            class="w-full"
                            :aria-invalid="!!form.errors.interest"
                            :aria-describedby="
                                form.errors.interest
                                    ? 'suggest-source-interest-error'
                                    : undefined
                            "
                        >
                            <SelectValue placeholder="Choose interest" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in liveInterests"
                                :key="option.slug"
                                :value="option.slug"
                                >{{ option.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError
                        id="suggest-source-interest-error"
                        :message="form.errors.interest"
                    />
                </div>
                <div class="space-y-1.5">
                    <Label for="suggest-source-reason">Reason</Label>
                    <Textarea
                        id="suggest-source-reason"
                        v-model="form.reason"
                        :rows="4"
                        maxlength="500"
                        :aria-invalid="!!form.errors.reason"
                        :aria-describedby="
                            form.errors.reason
                                ? 'suggest-source-reason-error'
                                : undefined
                        "
                    />
                    <p class="text-right text-xs text-muted-foreground">
                        {{ form.reason.length }} / 500
                    </p>
                    <InputError
                        id="suggest-source-reason-error"
                        :message="form.errors.reason"
                    />
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('update:open', false)"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="!canSubmit"
                        >Send suggestion</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
