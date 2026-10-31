<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { store } from '@/actions/App/Http/Controllers/InterestProposalController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { OnboardingCategory } from '@/types';

const props = defineProps<{
    open: boolean;
    categories: OnboardingCategory[];
}>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();
const form = useForm({ name: '', category: '', reason: '' });
const canSubmit = computed(
    () =>
        !form.processing &&
        form.name.trim().length >= 2 &&
        form.name.length <= 60 &&
        props.categories.some((category) => category.value === form.category) &&
        form.reason.length >= 10 &&
        form.reason.length <= 500,
);
watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);
function submit() {
    if (!canSubmit.value) return;
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Suggest an interest</DialogTitle>
                <DialogDescription
                    >Tell us what you would like to follow. We will review your
                    suggestion.</DialogDescription
                >
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <Label for="suggest-interest-name">Name</Label>
                    <Input
                        id="suggest-interest-name"
                        v-model="form.name"
                        maxlength="60"
                        :aria-invalid="!!form.errors.name"
                        :aria-describedby="
                            form.errors.name
                                ? 'suggest-interest-name-error'
                                : undefined
                        "
                    />
                    <InputError
                        id="suggest-interest-name-error"
                        :message="form.errors.name"
                    />
                </div>
                <div class="space-y-1.5">
                    <Label for="suggest-interest-category">Category</Label>
                    <Select v-model="form.category">
                        <SelectTrigger
                            id="suggest-interest-category"
                            class="w-full"
                            :aria-invalid="!!form.errors.category"
                            :aria-describedby="
                                form.errors.category
                                    ? 'suggest-interest-category-error'
                                    : undefined
                            "
                        >
                            <SelectValue placeholder="Choose category" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in categories"
                                :key="option.value"
                                :value="option.value"
                                >{{ option.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError
                        id="suggest-interest-category-error"
                        :message="form.errors.category"
                    />
                </div>
                <div class="space-y-1.5">
                    <Label for="suggest-interest-reason">Reason</Label>
                    <Textarea
                        id="suggest-interest-reason"
                        v-model="form.reason"
                        :rows="4"
                        maxlength="500"
                        :aria-invalid="!!form.errors.reason"
                        :aria-describedby="
                            form.errors.reason
                                ? 'suggest-interest-reason-error'
                                : undefined
                        "
                    />
                    <p class="text-right text-xs text-muted-foreground">
                        {{ form.reason.length }} / 500
                    </p>
                    <InputError
                        id="suggest-interest-reason-error"
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
