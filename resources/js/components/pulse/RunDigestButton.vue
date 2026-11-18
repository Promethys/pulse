<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { run } from '@/routes/digest';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import type { PostKind } from '@/types/day';

const props = defineProps<{ kind: PostKind; label: string }>();
const form = useForm({ kind: props.kind });

function submit() {
    form.post(run.url(), { preserveScroll: true });
}
</script>

<template>
    <form @submit.prevent="submit">
        <Button type="submit" :disabled="form.processing">
            {{ form.processing ? 'Preparing…' : label }}
        </Button>
        <InputError :message="form.errors.kind" class="mt-2" />
    </form>
</template>
