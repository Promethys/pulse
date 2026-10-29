<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Search, Sunrise } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    about,
    aiKey,
    complete,
    interests,
    sources,
} from '@/actions/App/Http/Controllers/OnboardingController';
import AiKeyForm from '@/components/pulse/AiKeyForm.vue';
import CategoryGroup from '@/components/pulse/CategoryGroup.vue';
import DeliveryOptions from '@/components/pulse/DeliveryOptions.vue';
import PulseLogo from '@/components/pulse/PulseLogo.vue';
import SourceToggleRow from '@/components/pulse/SourceToggleRow.vue';
import StepIndicator from '@/components/pulse/StepIndicator.vue';
import ThemeToggle from '@/components/pulse/ThemeToggle.vue';
import { Button } from '@/components/ui/button';
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
import type {
    AiKeyInput,
    OnboardingProps,
    OnboardingSourceGroup,
} from '@/types/onboarding';

defineOptions({ layout: [] });
const props = defineProps<OnboardingProps>();
const steps = ['Interests', 'Sources', 'About you', 'AI key', 'Delivery'];
const step = ref(0);
const search = ref('');
const interestForm = useForm({
    interests: props.interests
        .filter((interest) => interest.selected && interest.status === 'live')
        .map((interest) => interest.slug),
});
const sourceForm = useForm({ subscriptions: {} as Record<number, boolean> });
function refreshSources(groups: OnboardingSourceGroup[]) {
    sourceForm.subscriptions = Object.fromEntries(
        groups.flatMap((group) =>
            group.sources.map((source) => [
                source.id,
                source.subscription_enabled,
            ]),
        ),
    );
}
watch(() => props.sources, refreshSources, { immediate: true });
const aboutForm = useForm({
    profile: props.settings.profile ?? '',
    timezone: props.settings.timezone,
});
const keyForm = useForm<AiKeyInput>({
    provider: props.providers[0]?.value ?? '',
    model: props.providers[0]?.suggested_models[0] ?? '',
    api_key: '',
    consent: false,
});
const deliveryForm = useForm({
    email_delivery: props.email_delivery,
    email: props.email,
});
const keyInput = computed({
    get: () => keyForm.data(),
    set: (value: AiKeyInput) => Object.assign(keyForm, value),
});
const filteredCategories = computed(() =>
    props.categories
        .map((category) => ({
            category,
            interests: props.interests.filter(
                (interest) =>
                    interest.category === category.value &&
                    interest.name
                        .toLowerCase()
                        .includes(search.value.toLowerCase()),
            ),
        }))
        .filter((group) => group.interests.length),
);
const forms = [interestForm, sourceForm, aboutForm, keyForm, deliveryForm];
const processing = computed(() => forms.some((form) => form.processing));
const errors = computed(() => Object.values(forms[step.value].errors));
const canContinue = computed(() => {
    if (processing.value) return false;
    if (step.value === 0) return interestForm.interests.length > 0;
    if (step.value === 3)
        return Boolean(
            keyForm.provider &&
            keyForm.model.trim() &&
            keyForm.api_key.trim() &&
            keyForm.consent,
        );
    return true;
});
function toggle(slug: string) {
    if (
        !props.interests.some(
            (interest) => interest.slug === slug && interest.status === 'live',
        )
    )
        return;
    interestForm.interests = interestForm.interests.includes(slug)
        ? interestForm.interests.filter((selected) => selected !== slug)
        : [...interestForm.interests, slug];
}
function next() {
    if (!canContinue.value) return;
    const advance = () => {
        step.value++;
    };
    switch (step.value) {
        case 0:
            interestForm.post(interests.url(), {
                preserveScroll: true,
                onSuccess: (page) => {
                    refreshSources(
                        page.props.sources as OnboardingSourceGroup[],
                    );
                    advance();
                },
            });
            break;
        case 1:
            sourceForm.put(sources.url(), {
                preserveScroll: true,
                onSuccess: advance,
            });
            break;
        case 2:
            aboutForm.put(about.url(), {
                preserveScroll: true,
                onSuccess: advance,
            });
            break;
        case 3:
            keyForm.post(aiKey.url(), {
                preserveScroll: true,
                onSuccess: () => {
                    keyForm.reset('api_key', 'consent');
                    advance();
                },
            });
            break;
        case 4:
            deliveryForm.post(complete.url());
            break;
    }
}
</script>

<template>
    <Head title="Set up your digest" />
    <div class="min-h-screen bg-background">
        <header
            class="mx-auto flex max-w-3xl items-center justify-between px-4 py-5"
        >
            <PulseLogo />
            <ThemeToggle />
        </header>
        <main class="mx-auto max-w-3xl px-4 pb-32 md:pb-12">
            <StepIndicator :steps="steps" :current="step" />
            <div class="mt-8">
                <section v-if="step === 0" class="space-y-6">
                    <div>
                        <h1 class="text-3xl font-semibold">
                            What do you follow?
                        </h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Pick at least one.
                            {{ interestForm.interests.length }} selected.
                        </p>
                    </div>
                    <div class="relative">
                        <Search
                            class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            v-model="search"
                            placeholder="Search interests"
                            aria-label="Search interests"
                            class="pl-9"
                        />
                    </div>
                    <div class="space-y-7">
                        <CategoryGroup
                            v-for="group in filteredCategories"
                            :key="group.category.value"
                            :category="group.category"
                            :interests="group.interests"
                            :selected="interestForm.interests"
                            @toggle="toggle"
                        />
                        <p
                            v-if="!filteredCategories.length"
                            class="text-sm text-muted-foreground"
                        >
                            No interest matches “{{ search }}”.
                        </p>
                    </div>
                </section>
                <section v-else-if="step === 1" class="space-y-6">
                    <div>
                        <h1 class="text-3xl font-semibold">Your sources</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            These are the default sources for your interests.
                            Turn off any you don't want.
                        </p>
                    </div>
                    <div
                        v-for="group in props.sources"
                        :key="group.interest.slug"
                    >
                        <h2 class="mb-2 text-lg font-semibold">
                            {{ group.interest.name }}
                        </h2>
                        <ul
                            class="divide-y divide-border rounded-2xl border border-border bg-card"
                        >
                            <SourceToggleRow
                                v-for="source in group.sources"
                                :key="source.id"
                                v-model="sourceForm.subscriptions[source.id]"
                                :source="source"
                            />
                        </ul>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        You can add your own sources later.
                    </p>
                </section>
                <section v-else-if="step === 2" class="space-y-6">
                    <div>
                        <h1 class="text-3xl font-semibold">About you</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Optional. Helps the AI pick and phrase what matters
                            to you.
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="about"
                            >Who are you and what do you want from Pulse?</Label
                        >
                        <Textarea
                            id="about"
                            v-model="aboutForm.profile"
                            :rows="6"
                            :maxlength="2000"
                            placeholder="Laravel developer, curious about AI agents, not interested in crypto. I like short summaries with a link to the source."
                        />
                        <p class="text-right text-xs text-muted-foreground">
                            {{ aboutForm.profile.length.toLocaleString('en') }}
                            / 2,000
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="timezone">Timezone</Label>
                        <Select v-model="aboutForm.timezone">
                            <SelectTrigger id="timezone" class="w-full"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="timezone in timezones"
                                    :key="timezone"
                                    :value="timezone"
                                    >{{ timezone }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                </section>
                <section v-else-if="step === 3" class="space-y-6">
                    <div>
                        <h1 class="text-3xl font-semibold">Your AI key</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Pulse uses your key to write your digest. Required
                            for the digest to run, but you can add it later.
                        </p>
                    </div>
                    <AiKeyForm v-model="keyInput" :providers="providers" />
                    <div
                        v-if="ai_keys.length"
                        class="space-y-2 text-sm text-muted-foreground"
                    >
                        <p v-for="key in ai_keys" :key="key.id">
                            {{
                                providers.find(
                                    (provider) =>
                                        provider.value === key.provider,
                                )?.label ?? key.provider
                            }}
                            · {{ key.model }} ·
                            <code
                                class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs"
                                >••••{{ key.key_suffix }}</code
                            >
                        </p>
                    </div>
                </section>
                <section v-else class="space-y-6">
                    <div>
                        <h1 class="text-3xl font-semibold">
                            Where to receive it
                        </h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Pick as many places as you like. You can change this
                            later in Settings.
                        </p>
                    </div>
                    <DeliveryOptions
                        v-model:email="deliveryForm.email"
                        v-model:email-delivery="deliveryForm.email_delivery"
                    />
                    <div
                        class="flex items-center gap-3 rounded-xl border border-primary-strong/30 bg-primary/40 p-4"
                    >
                        <Sunrise
                            class="size-6 text-primary-strong"
                            aria-hidden="true"
                        />
                        <div>
                            <p class="font-semibold">
                                Your first digest arrives tomorrow at
                                {{ settings.news_time }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                In the app{{
                                    deliveryForm.email_delivery
                                        ? ' and by email'
                                        : ''
                                }}.
                            </p>
                        </div>
                    </div>
                </section>
                <div
                    v-if="errors.length"
                    role="alert"
                    class="mt-4 space-y-1 text-sm text-destructive"
                >
                    <p v-for="(error, index) in errors" :key="index">
                        {{ error }}
                    </p>
                </div>
            </div>
        </main>
        <footer
            class="fixed inset-x-0 bottom-0 border-t border-border bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:static md:border-0 md:bg-transparent"
        >
            <div class="mx-auto flex max-w-3xl items-center gap-2 px-4 py-3">
                <Button
                    variant="ghost"
                    :disabled="step === 0 || processing"
                    @click="step--"
                    >Back</Button
                >
                <div class="ml-auto flex items-center gap-2">
                    <Button
                        v-if="step === 2 || step === 3"
                        variant="ghost"
                        :disabled="processing"
                        @click="step++"
                        >Skip for now</Button
                    >
                    <Button :disabled="!canContinue" @click="next">{{
                        step === 4 ? 'Go to Today' : 'Continue'
                    }}</Button>
                </div>
            </div>
            <p
                v-if="step === 0 && !interestForm.interests.length"
                class="pb-2 text-center text-xs text-muted-foreground"
            >
                Pick at least one interest to continue.
            </p>
        </footer>
    </div>
</template>
