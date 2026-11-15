<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    store,
    test,
    update,
} from '@/actions/App/Http/Controllers/SourcesController';
import InputError from '@/components/InputError.vue';
import HelpTip from '@/components/pulse/HelpTip.vue';
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
    address: '',
    interests: [],
});
const processing = ref(false);
const headlines = ref<string[]>([]);
const testErrors = ref<Record<string, string>>({});
const fieldErrors = computed(() => ({ ...props.errors, ...testErrors.value }));
const http = useHttp<
    Pick<SourceInput, 'type' | 'url' | 'api_key' | 'address'>,
    SourceTestResult
>({ type: 'feed', url: '', api_key: '', address: '' });
const testKey = computed(() =>
    JSON.stringify([
        form.value.type,
        form.value.url,
        form.value.api_key,
        form.value.address,
    ]),
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
            address: source?.address ?? '',
            interests: source?.interests.map((interest) => interest.slug) ?? [],
        };
        headlines.value = [];
        testErrors.value = {};
    },
    { immediate: true },
);
watch(testKey, () => {
    headlines.value = [];
});
for (const field of Object.keys(form.value) as (keyof SourceInput)[]) {
    watch(
        () => form.value[field],
        () => {
            delete testErrors.value[field];
            if (props.errors[field]) {
                const errors = { ...props.errors };
                delete errors[field];
                emit('errors', errors);
            }
        },
    );
}
function toggleInterest(slug: string, checked: boolean) {
    form.value.interests = checked
        ? [...form.value.interests, slug]
        : form.value.interests.filter((value) => value !== slug);
}
async function testSource(): Promise<SourceTestResult> {
    const key = testKey.value;
    testErrors.value = {};
    Object.assign(http, {
        type: form.value.type,
        url: form.value.url,
        api_key: form.value.api_key,
        address: form.value.type === 'resend_inbox' ? form.value.address : '',
    });
    try {
        const result = await http.submit(test(), {
            onError: (errors) => {
                if (key === testKey.value && props.open)
                    testErrors.value = errors;
            },
        });
        if (!result)
            return {
                ok: false,
                message: 'Fix the highlighted field.',
                headlines: [],
            };
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
        http.address = '';
    }
}
function save() {
    if (!canSave.value) return;
    testErrors.value = {};
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
        router.put(update.url(props.source.id), { ...form.value }, options);
    else router.post(store.url(), { ...form.value }, options);
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
            <p v-if="!source" class="text-sm text-muted-foreground">
                You can save up to 30 custom sources.
            </p>
            <form novalidate class="space-y-4" @submit.prevent="save">
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
                        <RadioGroupItem
                            id="source-feed"
                            value="feed"
                            class="focus-ring"
                        />
                        Feed URL
                    </Label>
                    <Label
                        for="source-newsletter"
                        class="flex items-center gap-2 rounded-lg border border-border p-3 font-normal has-[[data-state=checked]]:border-primary-strong"
                    >
                        <RadioGroupItem
                            id="source-newsletter"
                            value="resend_inbox"
                            class="focus-ring"
                        />
                        Newsletter inbox
                    </Label>
                </RadioGroup>
                <p v-if="source" class="text-sm text-muted-foreground">
                    A source’s type cannot be changed. Create a new source to
                    use another type.
                </p>
                <InputError :message="fieldErrors.type" />
                <div v-if="form.type === 'feed'" class="space-y-1.5">
                    <span class="inline-flex items-center gap-1">
                        <Label for="source-url">Feed URL (required)</Label>
                        <HelpTip
                            label="Feeds"
                            text="A feed lists a site’s latest posts so Pulse can read them automatically."
                        />
                    </span>
                    <Input
                        id="source-url"
                        v-model="form.url"
                        maxlength="2000"
                        placeholder="https://freek.dev/feed"
                        :aria-invalid="!!fieldErrors.url"
                        :aria-describedby="
                            fieldErrors.url ? 'source-url-error' : undefined
                        "
                    />
                    <p class="text-sm text-muted-foreground">
                        Paste the site’s public RSS or Atom feed URL. Look for
                        an RSS or Subscribe link on the site.
                    </p>
                    <InputError
                        id="source-url-error"
                        :message="fieldErrors.url"
                    />
                </div>
                <div v-else class="space-y-1.5">
                    <MaskedSecretInput
                        id="source-key"
                        v-model="form.api_key"
                        label="Resend API key"
                        hint="Required for a new newsletter inbox."
                        :placeholder="
                            source?.has_key
                                ? 'Key stored, leave empty to keep it'
                                : 're_…'
                        "
                    />
                    <p class="text-sm text-muted-foreground">
                        Connect a
                        <span class="inline-flex items-center gap-1">
                            Resend account
                            <HelpTip
                                label="Resend"
                                text="Resend is an email service. This does not connect your Gmail or Outlook inbox."
                            />
                        </span>
                        that receives your newsletters. Pulse reads recent
                        received emails from that account.
                    </p>
                    <InputError :message="fieldErrors.api_key" />
                </div>
                <div v-if="form.type === 'resend_inbox'" class="space-y-1.5">
                    <Label for="source-address">Receiving address</Label>
                    <Input
                        id="source-address"
                        v-model="form.address"
                        type="email"
                        placeholder="newsletters@example.com"
                        maxlength="254"
                        :aria-invalid="!!fieldErrors.address"
                        :aria-describedby="
                            fieldErrors.address
                                ? 'source-address-help source-address-error'
                                : 'source-address-help'
                        "
                    />
                    <p
                        id="source-address-help"
                        class="text-sm text-muted-foreground"
                    >
                        Only keep emails sent to this address. Leave empty to
                        read the whole inbox.
                    </p>
                    <InputError
                        id="source-address-error"
                        :message="fieldErrors.address"
                    />
                </div>
                <div class="space-y-1.5">
                    <Label for="source-name">Name (required)</Label>
                    <Input
                        id="source-name"
                        v-model="form.name"
                        maxlength="100"
                        placeholder="Freek.dev"
                        :aria-invalid="!!fieldErrors.name"
                        :aria-describedby="
                            fieldErrors.name ? 'source-name-error' : undefined
                        "
                    />
                    <InputError
                        id="source-name-error"
                        :message="fieldErrors.name"
                    />
                </div>
                <fieldset class="space-y-1.5">
                    <legend class="text-sm font-medium">
                        Interests it belongs to (at least one required)
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
                    <InputError :message="fieldErrors.interests" />
                    <InputError
                        v-for="(slug, index) in form.interests"
                        :key="slug"
                        :message="fieldErrors[`interests.${index}`]"
                    />
                </fieldset>
                <p
                    v-if="source?.has_key && form.type === 'resend_inbox'"
                    class="text-sm text-muted-foreground"
                >
                    To test here, enter a Resend key. Leave it blank to keep the
                    saved key when saving.
                </p>
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
                <p
                    v-if="!canSave && !processing"
                    class="text-sm text-muted-foreground"
                >
                    Enter a name, choose at least one interest, and add a feed
                    URL or Resend key.
                </p>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('update:open', false)"
                        class="focus-ring hover:bg-accent hover:text-accent-foreground active:bg-accent active:text-accent-foreground dark:active:bg-accent"
                        >Cancel</Button
                    >
                    <Button
                        type="submit"
                        :disabled="!canSave"
                        class="focus-ring"
                        >{{ source ? 'Save' : 'Add' }}</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
