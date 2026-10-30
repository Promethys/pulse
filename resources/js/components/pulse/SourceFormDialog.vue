<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import MaskedSecretInput from '@/components/pulse/MaskedSecretInput.vue';
import TestButton from '@/components/pulse/TestButton.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import type {
    ManagedSource,
    OnboardingInterest,
    SourceInput,
    SourceTestResult,
} from '@/types';

const props = defineProps<{
    open: boolean;
    source: ManagedSource | null;
    interests: OnboardingInterest[];
    errors: Record<string, string>;
}>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    errors: [value: Record<string, string>];
}>();
const form = ref<SourceInput>({
    type: 'feed',
    name: '',
    url: '',
    api_key: '',
    interests: [],
});
const processing = ref(false);
const headlines = ref<string[]>([]);
const http = useHttp<
    Pick<SourceInput, 'type' | 'url' | 'api_key'>,
    SourceTestResult
>({ type: 'feed', url: '', api_key: '' });
const testKey = computed(() =>
    JSON.stringify([form.value.type, form.value.url, form.value.api_key]),
);
const canTest = computed(() =>
    Boolean(
        form.value.type === 'feed'
            ? form.value.url.trim()
            : form.value.api_key.trim(),
    ),
);
const canSave = computed(
    () =>
        !processing.value &&
        form.value.name.trim() &&
        form.value.interests.length &&
        (canTest.value ||
            (form.value.type === 'resend_inbox' && props.source?.has_key)),
);
watch(
    () => [props.open, props.source] as const,
    () => {
        const source = props.source;
        form.value = {
            type: source?.type ?? 'feed',
            name: source?.name ?? '',
            url: source?.url ?? '',
            api_key: '',
            interests: source?.interests.map((interest) => interest.slug) ?? [],
        };
        headlines.value = [];
    },
    { immediate: true },
);
watch(testKey, () => {
    headlines.value = [];
});
function toggleInterest(slug: string, checked: boolean) {
    form.value.interests = checked
        ? [...form.value.interests, slug]
        : form.value.interests.filter((value) => value !== slug);
}
async function testSource(): Promise<SourceTestResult> {
    const key = testKey.value;
    Object.assign(http, {
        type: form.value.type,
        url: form.value.url,
        api_key: form.value.api_key,
    });
    try {
        const result = await http.submit({
            method: 'post',
            url: '/sources/test',
        });
        if (key === testKey.value && props.open)
            headlines.value = result.headlines.slice(0, 5);
        return result;
    } catch {
        return {
            ok: false,
            message: 'Could not read this source. Please try again.',
            headlines: [],
        };
    } finally {
        http.api_key = '';
    }
}
function save() {
    if (!canSave.value) return;
    processing.value = true;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
        },
        onError: (errors: Record<string, string>) => {
            emit('errors', errors);
        },
        onFinish: () => {
            processing.value = false;
        },
    };
    if (props.source)
        router.put(`/sources/${props.source.id}`, { ...form.value }, options);
    else router.post('/sources', { ...form.value }, options);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{
                    source ? 'Edit source' : 'Add a source'
                }}</DialogTitle>
                <DialogDescription
                    >Only you will see this source.</DialogDescription
                >
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="save">
                <RadioGroup
                    v-model="form.type"
                    :disabled="!!source"
                    aria-label="Source kind"
                    class="grid grid-cols-2 gap-2"
                >
                    <Label
                        for="source-feed"
                        class="flex items-center gap-2 rounded-lg border border-border p-3 font-normal has-[[data-state=checked]]:border-primary-strong"
                    >
                        <RadioGroupItem id="source-feed" value="feed" /> Feed
                        URL
                    </Label>
                    <Label
                        for="source-newsletter"
                        class="flex items-center gap-2 rounded-lg border border-border p-3 font-normal has-[[data-state=checked]]:border-primary-strong"
                    >
                        <RadioGroupItem
                            id="source-newsletter"
                            value="resend_inbox"
                        />
                        Newsletter inbox
                    </Label>
                </RadioGroup>
                <InputError :message="errors.type" />
                <div v-if="form.type === 'feed'" class="space-y-1.5">
                    <Label for="source-url">Feed URL</Label>
                    <Input
                        id="source-url"
                        v-model="form.url"
                        maxlength="2000"
                        placeholder="https://freek.dev/feed"
                        :aria-invalid="!!errors.url"
                        :aria-describedby="
                            errors.url ? 'source-url-error' : undefined
                        "
                    />
                    <InputError id="source-url-error" :message="errors.url" />
                </div>
                <div v-else class="space-y-1.5">
                    <MaskedSecretInput
                        id="source-key"
                        v-model="form.api_key"
                        label="Resend API key"
                        hint="Used to read the newsletter from your own Resend account."
                        :placeholder="
                            source?.has_key
                                ? 'Key stored, leave empty to keep it'
                                : 're_…'
                        "
                    />
                    <InputError :message="errors.api_key" />
                </div>
                <div class="space-y-1.5">
                    <Label for="source-name">Name</Label>
                    <Input
                        id="source-name"
                        v-model="form.name"
                        maxlength="100"
                        placeholder="Freek.dev"
                        :aria-invalid="!!errors.name"
                        :aria-describedby="
                            errors.name ? 'source-name-error' : undefined
                        "
                    />
                    <InputError id="source-name-error" :message="errors.name" />
                </div>
                <fieldset class="space-y-1.5">
                    <legend class="text-sm font-medium">
                        Interests it belongs to
                    </legend>
                    <div class="flex flex-wrap gap-3">
                        <Label
                            v-for="interest in interests"
                            :key="interest.slug"
                            :for="`source-interest-${interest.id}`"
                            class="flex items-center gap-2 font-normal"
                        >
                            <Checkbox
                                :id="`source-interest-${interest.id}`"
                                :model-value="
                                    form.interests.includes(interest.slug)
                                "
                                @update:model-value="
                                    toggleInterest(
                                        interest.slug,
                                        $event === true,
                                    )
                                "
                            />
                            {{ interest.name }}
                        </Label>
                    </div>
                    <InputError :message="errors.interests" />
                    <InputError
                        v-for="(slug, index) in form.interests"
                        :key="slug"
                        :message="errors[`interests.${index}`]"
                    />
                </fieldset>
                <TestButton
                    label="Test now"
                    :on-test="testSource"
                    :disabled="!canTest"
                    :reset-key="testKey"
                />
                <ul
                    v-if="headlines.length"
                    class="space-y-1 rounded-lg border border-border p-3 text-sm"
                >
                    <li
                        v-for="(headline, index) in headlines"
                        :key="index"
                        class="line-clamp-1"
                        :title="headline"
                    >
                        {{ headline }}
                    </li>
                </ul>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('update:open', false)"
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="!canSave">{{
                        source ? 'Save' : 'Add'
                    }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
