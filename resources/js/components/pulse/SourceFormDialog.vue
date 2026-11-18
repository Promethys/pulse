<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { Eye, EyeOff } from '@lucide/vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { show as docsArticle } from '@/routes/docs';
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
    imap_host: 'imap.gmail.com',
    imap_username: '',
    imap_password: '',
    imap_folder: 'INBOX',
    interests: [],
});
const imapProviders = [
    { label: 'Gmail', host: 'imap.gmail.com' },
    { label: 'iCloud', host: 'imap.mail.me.com' },
    { label: 'Yahoo', host: 'imap.mail.yahoo.com' },
    { label: 'Fastmail', host: 'imap.fastmail.com' },
    { label: 'Other', host: 'other' },
];
const imapProvider = ref('imap.gmail.com');
const showPassword = ref(false);
const processing = ref(false);
const headlines = ref<string[]>([]);
const testErrors = ref<Record<string, string>>({});
const fieldErrors = computed(() => ({ ...props.errors, ...testErrors.value }));
const http = useHttp<Omit<SourceInput, 'name' | 'interests'>, SourceTestResult>(
    {
        type: 'feed',
        url: '',
        api_key: '',
        address: '',
        imap_host: '',
        imap_username: '',
        imap_password: '',
        imap_folder: 'INBOX',
    },
);
const testKey = computed(() =>
    JSON.stringify([
        form.value.type,
        form.value.url,
        form.value.api_key,
        form.value.address,
        form.value.imap_host,
        form.value.imap_username,
        form.value.imap_password,
        form.value.imap_folder,
    ]),
);
const hasImapAccount = computed(() =>
    Boolean(form.value.imap_host.trim() && form.value.imap_username.trim()),
);
const canTest = computed(() => {
    if (form.value.type === 'feed') return Boolean(form.value.url.trim());
    if (form.value.type === 'imap_inbox')
        return hasImapAccount.value && Boolean(form.value.imap_password.trim());
    return Boolean(form.value.api_key.trim());
});
const canSave = computed(
    () =>
        !processing.value &&
        form.value.name.trim() &&
        form.value.interests.length &&
        (canTest.value ||
            (props.source?.has_key &&
                (form.value.type === 'resend_inbox' ||
                    (form.value.type === 'imap_inbox' &&
                        hasImapAccount.value)))),
);
watch(
    () => [props.open, props.source] as const,
    () => {
        const source = props.source;
        form.value = {
            type:
                source?.type === 'imap_inbox' || source?.type === 'resend_inbox'
                    ? source.type
                    : 'feed',
            name: source?.name ?? '',
            url: source?.url ?? '',
            api_key: '',
            address: source?.address ?? '',
            imap_host: source ? (source.imap?.host ?? '') : 'imap.gmail.com',
            imap_username: source?.imap?.username ?? '',
            imap_password: '',
            imap_folder: source?.imap?.folder ?? 'INBOX',
            interests: source?.interests.map((interest) => interest.slug) ?? [],
        };
        imapProvider.value =
            imapProviders.find(
                (provider) => provider.host === form.value.imap_host,
            )?.host ?? 'other';
        showPassword.value = false;
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
function selectType(value: unknown) {
    if (value === 'feed' || value === 'resend_inbox' || value === 'imap_inbox')
        form.value.type = value;
}
function selectImapProvider(value: unknown) {
    if (typeof value !== 'string') return;
    imapProvider.value = value;
    form.value.imap_host = value === 'other' ? '' : value;
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
        imap_host: form.value.imap_host,
        imap_username: form.value.imap_username,
        imap_password: form.value.imap_password,
        imap_folder: form.value.imap_folder,
    });
    try {
        const result = await http.submit(test(), {
            onError: (errors) => {
                if (key === testKey.value && props.open)
                    testErrors.value = { ...errors };
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
        http.imap_password = '';
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
        <DialogContent class="max-h-[90vh] min-w-0 overflow-y-auto">
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
            <form novalidate class="min-w-0 space-y-4" @submit.prevent="save">
                <RadioGroup
                    :model-value="form.type"
                    @update:model-value="selectType"
                    :disabled="!!source"
                    aria-label="Source kind"
                    class="grid gap-2 sm:grid-cols-3"
                >
                    <Label
                        for="source-feed"
                        class="flex items-center gap-2 rounded-lg border border-border p-3 font-normal has-[[data-state=checked]]:border-primary-strong sm:px-1.5"
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
                        class="flex items-center gap-2 rounded-lg border border-border p-3 font-normal has-[[data-state=checked]]:border-primary-strong sm:px-1.5"
                    >
                        <RadioGroupItem
                            id="source-newsletter"
                            value="resend_inbox"
                            class="focus-ring"
                        />
                        Resend inbox
                    </Label>
                    <Label
                        for="source-imap"
                        class="flex items-center gap-2 rounded-lg border border-border p-3 font-normal has-[[data-state=checked]]:border-primary-strong sm:px-1.5"
                    >
                        <RadioGroupItem
                            id="source-imap"
                            value="imap_inbox"
                            class="focus-ring"
                        />
                        Email folder
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
                <div
                    v-else-if="form.type === 'resend_inbox'"
                    class="space-y-1.5"
                >
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
                <template v-if="form.type === 'imap_inbox'">
                    <p class="text-sm text-muted-foreground">
                        Pulse signs in with an app password, never your main
                        password, and reads only the folder you choose over a
                        secure connection. Emails stay unread and are never
                        moved or deleted. The password is stored encrypted.
                        <a
                            :href="
                                docsArticle.url({
                                    section: 'interests-and-sources',
                                    article:
                                        'read-newsletters-from-your-mailbox',
                                })
                            "
                            target="_blank"
                            rel="noopener"
                            class="whitespace-nowrap underline underline-offset-2 focus-ring hover:text-foreground active:text-foreground"
                            >How to set it up</a
                        >
                    </p>
                    <div class="space-y-1.5">
                        <Label for="source-imap-provider">Provider</Label>
                        <Select
                            :model-value="imapProvider"
                            @update:model-value="selectImapProvider"
                        >
                            <SelectTrigger
                                id="source-imap-provider"
                                class="w-full focus-ring"
                                :aria-invalid="
                                    imapProvider !== 'other' &&
                                    !!fieldErrors.imap_host
                                "
                                :aria-describedby="
                                    imapProvider !== 'other' &&
                                    fieldErrors.imap_host
                                        ? 'source-imap-host-error'
                                        : undefined
                                "
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="provider in imapProviders"
                                    :key="provider.host"
                                    :value="provider.host"
                                    >{{ provider.label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError
                            v-if="imapProvider !== 'other'"
                            id="source-imap-host-error"
                            :message="fieldErrors.imap_host"
                        />
                    </div>
                    <div v-if="imapProvider === 'other'" class="space-y-1.5">
                        <Label for="source-imap-host">Mail server</Label>
                        <Input
                            id="source-imap-host"
                            v-model="form.imap_host"
                            placeholder="imap.example.com"
                            class="focus-ring"
                            :aria-invalid="
                                imapProvider === 'other' &&
                                !!fieldErrors.imap_host
                            "
                            :aria-describedby="
                                imapProvider === 'other' &&
                                fieldErrors.imap_host
                                    ? 'source-imap-host-error'
                                    : undefined
                            "
                        />
                        <InputError
                            id="source-imap-host-error"
                            :message="fieldErrors.imap_host"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="source-imap-username"
                            >Email or username</Label
                        >
                        <Input
                            id="source-imap-username"
                            v-model="form.imap_username"
                            placeholder="you@example.com"
                            class="focus-ring"
                            :aria-invalid="!!fieldErrors.imap_username"
                            :aria-describedby="
                                fieldErrors.imap_username
                                    ? 'source-imap-username-error'
                                    : undefined
                            "
                        />
                        <InputError
                            id="source-imap-username-error"
                            :message="fieldErrors.imap_username"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="source-imap-password">App password</Label>
                        <div class="relative">
                            <Input
                                id="source-imap-password"
                                v-model="form.imap_password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="off"
                                :spellcheck="false"
                                maxlength="500"
                                class="pr-10 font-mono text-sm focus-ring"
                                :aria-invalid="!!fieldErrors.imap_password"
                                :aria-describedby="
                                    [
                                        source?.has_key
                                            ? 'source-imap-password-help'
                                            : '',
                                        fieldErrors.imap_password
                                            ? 'source-imap-password-error'
                                            : '',
                                    ]
                                        .filter(Boolean)
                                        .join(' ') || undefined
                                "
                            />
                            <button
                                type="button"
                                :aria-label="showPassword ? 'Hide' : 'Show'"
                                :aria-pressed="showPassword"
                                class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-muted-foreground focus-ring hover:text-foreground active:text-foreground"
                                @click="showPassword = !showPassword"
                            >
                                <EyeOff
                                    v-if="showPassword"
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                <Eye v-else class="size-4" aria-hidden="true" />
                            </button>
                        </div>
                        <p
                            v-if="source?.has_key"
                            id="source-imap-password-help"
                            class="text-xs text-muted-foreground"
                        >
                            Leave blank to keep the saved password.
                        </p>
                        <InputError
                            id="source-imap-password-error"
                            :message="fieldErrors.imap_password"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="source-imap-folder">Folder or label</Label>
                        <Input
                            id="source-imap-folder"
                            v-model="form.imap_folder"
                            placeholder="INBOX"
                            class="focus-ring"
                            :aria-invalid="!!fieldErrors.imap_folder"
                            :aria-describedby="
                                fieldErrors.imap_folder
                                    ? 'source-imap-folder-help source-imap-folder-error'
                                    : 'source-imap-folder-help'
                            "
                        />
                        <p
                            id="source-imap-folder-help"
                            class="text-sm text-muted-foreground"
                        >
                            {{
                                imapProvider === 'imap.gmail.com'
                                    ? 'For Gmail, use a label such as Newsletters.'
                                    : 'The folder to read, INBOX by default.'
                            }}
                        </p>
                        <InputError
                            id="source-imap-folder-error"
                            :message="fieldErrors.imap_folder"
                        />
                    </div>
                </template>
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
                    class="min-w-0 space-y-1 rounded-lg border border-border p-3 text-sm"
                >
                    <li
                        v-for="(headline, index) in headlines"
                        :key="index"
                        class="line-clamp-1 min-w-0 [overflow-wrap:anywhere] break-words"
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
                    URL, Resend key or email folder connection.
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
