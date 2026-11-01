<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Archive,
    GraduationCap,
    KeyRound,
    Mail,
    Search,
    ShieldCheck,
    Sun,
    Tag,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import DiscordIcon from '@/components/pulse/DiscordIcon.vue';
import FeatureSection from '@/components/pulse/FeatureSection.vue';
import InterestChip from '@/components/pulse/InterestChip.vue';
import NewsItemRow from '@/components/pulse/NewsItemRow.vue';
import StickySectionNav from '@/components/pulse/StickySectionNav.vue';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/PublicLayout.vue';
import {
    publicInterests,
    publicLearning,
    publicNews,
    publicProviders,
} from '@/lib/publicSamples';
import { register } from '@/routes';
import { index as docs } from '@/routes/docs';

defineOptions({ layout: [] });

const page = usePage<{ canRegister: boolean }>();
const selected = ref(
    publicInterests
        .filter((interest) => interest.selected)
        .map((interest) => interest.id),
);
const sections = [
    { id: 'interests', label: 'Interests and sources' },
    { id: 'digest', label: 'A digest written for you' },
    { id: 'tips-topics', label: 'Tips and topics' },
    { id: 'reading', label: 'Read it your way' },
    { id: 'delivery', label: 'Delivered where you are' },
    { id: 'keys', label: 'Your keys, your costs' },
    { id: 'security', label: 'Security and privacy' },
];
const reading = [
    { icon: Sun, label: 'Today', note: 'This morning’s digest' },
    { icon: Archive, label: 'Archive', note: 'Browse past days' },
    {
        icon: Search,
        label: 'Search',
        note: 'Text, tag, source and date filters',
    },
    { icon: GraduationCap, label: 'Learning', note: 'Your tips and topics' },
    { icon: Tag, label: 'Tags', note: 'Explore a topic’s history' },
];

function toggleInterest(id: number) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((value) => value !== id)
        : [...selected.value, id];
}
</script>

<template>
    <PublicLayout>
        <Head title="Features">
            <meta
                name="description"
                content="Explore Pulse: interests and sources, personalized daily news, tips and topics, Discord and email delivery, your own AI keys and account security."
            />
        </Head>
        <div class="mx-auto max-w-5xl px-4 py-10 md:py-14">
            <header class="mb-10 max-w-2xl">
                <h1 class="text-3xl font-semibold md:text-4xl">Features</h1>
                <p class="mt-2 leading-relaxed text-muted-foreground">
                    A calmer way to keep up. Explore the daily digest, learning
                    posts and settings with sample content from the product.
                </p>
            </header>
            <div class="lg:flex lg:gap-10">
                <StickySectionNav :sections="sections" />
                <div class="min-w-0 flex-1">
                    <FeatureSection
                        id="interests"
                        kicker="Sources"
                        title="Your interests and sources"
                        body="Choose from 50+ interests, from Artificial Intelligence to Food, Finance and Sports. Each comes with reviewed sources you can switch off individually. Add your own RSS or Atom feeds and newsletters through a dedicated inbox. Sources also include GitHub trending. New catalog proposals are reviewed before they are added."
                    >
                        <div class="p-4">
                            <p class="mb-3 text-xs text-muted-foreground">
                                Sample selection. Try the interests below;
                                nothing is saved.
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <InterestChip
                                    v-for="interest in publicInterests"
                                    :key="interest.id"
                                    :interest="interest"
                                    :selected="selected.includes(interest.id)"
                                    @toggle="toggleInterest(interest.id)"
                                />
                            </div>
                            <ul
                                class="mt-4 divide-y divide-border rounded-xl border border-border text-sm"
                            >
                                <li
                                    class="flex items-center justify-between gap-3 p-3"
                                >
                                    <span>Laravel News</span
                                    ><span class="text-xs text-muted-foreground"
                                        >Feed</span
                                    >
                                </li>
                                <li
                                    class="flex items-center justify-between gap-3 p-3"
                                >
                                    <span>GitHub trending</span
                                    ><span class="text-xs text-muted-foreground"
                                        >Repositories</span
                                    >
                                </li>
                                <li
                                    class="flex items-center justify-between gap-3 p-3"
                                >
                                    <span>Your newsletters</span
                                    ><span class="text-xs text-muted-foreground"
                                        >Dedicated inbox</span
                                    >
                                </li>
                            </ul>
                        </div>
                    </FeatureSection>
                    <FeatureSection
                        id="digest"
                        kicker="The digest"
                        title="A digest written for you"
                        body="Write a short profile describing what you care about and what to skip. The AI uses it to choose and summarize your news. Each morning, read items grouped by topic with a short summary and a link to the original. News arrives at 09:00 by default in your timezone, and you can change the time."
                        flip
                    >
                        <div class="px-4 py-2">
                            <p class="pt-2 text-xs text-muted-foreground">
                                Sample news
                            </p>
                            <NewsItemRow
                                v-for="item in publicNews"
                                :key="item.id"
                                :item="item"
                            />
                        </div>
                    </FeatureSection>
                    <FeatureSection
                        id="tips-topics"
                        kicker="Learning"
                        title="Tips and topics"
                        body="Tech interests get a second post: a developer tip you can put into practice, and a topic of the day with links to read further. Your profile helps tailor the explanation to your stack. Learning arrives at 09:30 by default in your timezone. You can change the time."
                    >
                        <div class="space-y-4 p-4">
                            <article
                                v-for="post in publicLearning"
                                :key="post.kind"
                                class="rounded-xl border border-border bg-primary/10 p-4"
                            >
                                <p
                                    class="text-xs font-semibold tracking-wider text-primary-strong uppercase"
                                >
                                    Sample {{ post.kind }}
                                </p>
                                <h3 class="mt-1 text-lg font-semibold">
                                    {{ post.title }}
                                </h3>
                                <p
                                    class="mt-3 text-sm leading-relaxed text-muted-foreground"
                                >
                                    {{ post.body }}
                                </p>
                            </article>
                        </div>
                    </FeatureSection>
                    <FeatureSection
                        id="reading"
                        kicker="Reading"
                        title="Read it your way"
                        body="Start with Today, revisit a past day from the calendar, or browse the archive. Search by text, tag, source and date. Tag pages show how a subject appears over time, and the learning library keeps your tips and topics together."
                        flip
                    >
                        <ul class="divide-y divide-border">
                            <li
                                v-for="item in reading"
                                :key="item.label"
                                class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm"
                            >
                                <component
                                    :is="item.icon"
                                    class="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <span class="font-medium">{{ item.label }}</span
                                ><span
                                    class="ml-auto text-xs text-muted-foreground"
                                    >{{ item.note }}</span
                                >
                            </li>
                        </ul>
                    </FeatureSection>
                    <FeatureSection
                        id="delivery"
                        kicker="Delivery"
                        title="Delivered where you are"
                        body="Your digest is always in the app. Add Discord destinations through the Pulse bot or a webhook, or receive it by email. Each destination can receive news, learning, or both. Test a destination from settings before the next digest."
                    >
                        <ul class="divide-y divide-border text-sm">
                            <li class="flex items-center gap-3 px-4 py-4">
                                <Sun
                                    class="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <div>
                                    <p class="font-semibold">In the app</p>
                                    <p class="text-xs text-muted-foreground">
                                        Always available
                                    </p>
                                </div>
                            </li>
                            <li class="flex items-center gap-3 px-4 py-4">
                                <DiscordIcon
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                <div>
                                    <p class="font-semibold">Discord</p>
                                    <p class="text-xs text-muted-foreground">
                                        Bot or webhook, across your servers
                                    </p>
                                </div>
                            </li>
                            <li class="flex items-center gap-3 px-4 py-4">
                                <Mail
                                    class="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <div>
                                    <p class="font-semibold">Email</p>
                                    <p class="text-xs text-muted-foreground">
                                        News, learning, or both
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </FeatureSection>
                    <FeatureSection
                        id="keys"
                        kicker="AI keys"
                        title="Your keys, your costs"
                        body="Bring a key from Gemini, Anthropic, OpenAI, Groq, OpenRouter or Z.AI. Some providers have free tiers, and yours may charge beyond them. Keys are stored encrypted and shown only as their last four characters. Order several keys as a fallback chain if a provider is temporarily unavailable or out of quota."
                        flip
                    >
                        <div class="p-4">
                            <div
                                class="flex items-center gap-2 text-sm font-semibold"
                            >
                                <KeyRound
                                    class="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />Choose your provider
                            </div>
                            <ul class="mt-3 grid grid-cols-2 gap-2">
                                <li
                                    v-for="provider in publicProviders"
                                    :key="provider"
                                    class="rounded-xl border border-border p-3 text-sm font-medium"
                                >
                                    {{ provider }}
                                </li>
                            </ul>
                        </div>
                    </FeatureSection>
                    <FeatureSection
                        id="security"
                        kicker="Account"
                        title="Security and privacy"
                        body="Protect your account with two-factor authentication and passkeys. Your profile and selected content are sent to your chosen AI provider to prepare your digests, under that provider’s terms. You can delete your account from settings."
                    >
                        <ul class="divide-y divide-border text-sm">
                            <li class="flex items-center gap-3 px-4 py-4">
                                <ShieldCheck
                                    class="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />Two-factor authentication
                            </li>
                            <li class="flex items-center gap-3 px-4 py-4">
                                <KeyRound
                                    class="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />Passkeys
                            </li>
                            <li class="flex items-center gap-3 px-4 py-4">
                                <Trash2
                                    class="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />Delete your account from settings
                            </li>
                        </ul>
                    </FeatureSection>
                    <div
                        v-if="!page.props.auth.user && page.props.canRegister"
                        class="mt-4 rounded-3xl bg-primary/25 p-6 text-center md:p-10 dark:border dark:border-border dark:bg-card"
                    >
                        <h2 class="text-2xl font-semibold">
                            Try it tomorrow morning.
                        </h2>
                        <div class="mt-4 flex flex-wrap justify-center gap-2">
                            <Button as-child
                                ><Link :href="register()"
                                    >Create an account</Link
                                ></Button
                            >
                            <Button variant="outline" as-child
                                ><Link :href="docs()"
                                    >Read the docs</Link
                                ></Button
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
