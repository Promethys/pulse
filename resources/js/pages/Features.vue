<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import FeatureSection from '@/components/pulse/FeatureSection.vue';
import ProductShot from '@/components/pulse/ProductShot.vue';
import StickySectionNav from '@/components/pulse/StickySectionNav.vue';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { register } from '@/routes';
import { index as docs } from '@/routes/docs';

defineOptions({ layout: [] });

const page = usePage<{ canRegister: boolean }>();
const sections = [
    { id: 'interests', label: 'Interests and sources' },
    { id: 'digest', label: 'A digest written for you' },
    { id: 'tips-topics', label: 'Tips and topics' },
    { id: 'reading', label: 'Read it your way' },
    { id: 'delivery', label: 'Delivered where you are' },
    { id: 'keys', label: 'Your keys, your costs' },
    { id: 'security', label: 'Security and privacy' },
];
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
                    A calmer way to keep up. Here is the daily digest, learning
                    posts and settings, with sample data.
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
                        <ProductShot
                            name="sources-mobile"
                            alt="The Sources page, with interests and their sources switched on"
                        />
                    </FeatureSection>
                    <FeatureSection
                        id="digest"
                        kicker="The digest"
                        title="A digest written for you"
                        body="Write a short profile describing what you care about and what to skip. The AI uses it to choose and summarize your news. Each day, read items grouped by topic with a short summary and a link to the original. News arrives at 09:00 by default in your timezone, and you can change the time."
                        flip
                    >
                        <ProductShot
                            name="today-mobile"
                            alt="A day in Pulse, with news grouped by topic"
                        />
                    </FeatureSection>
                    <FeatureSection
                        id="tips-topics"
                        kicker="Learning"
                        title="Tips and topics"
                        body="Tech interests get a second post: a developer tip you can put into practice, and a topic of the day with links to read further. Your profile helps tailor the explanation to your stack. Learning arrives at 09:30 by default in your timezone. You can change the time."
                    >
                        <ProductShot
                            name="learning-mobile"
                            alt="The Learning page, with saved tips and topics"
                        />
                    </FeatureSection>
                    <FeatureSection
                        id="reading"
                        kicker="Reading"
                        title="Read it your way"
                        body="Start with Today, revisit a past day from the calendar, or browse the archive. Search by text, tag, source and date. Tag pages show how a subject appears over time, and the learning library keeps your tips and topics together."
                        flip
                    >
                        <ProductShot
                            name="search-mobile"
                            alt="Search results with filters"
                        />
                    </FeatureSection>
                    <FeatureSection
                        id="delivery"
                        kicker="Delivery"
                        title="Delivered where you are"
                        body="Your digest is always in the app. Add Discord destinations through the Pulse bot or a webhook, or receive it by email. Each destination can receive news, learning, or both. Test a destination from settings before the next digest."
                    >
                        <ProductShot
                            name="delivery-mobile"
                            alt="Delivery settings, with Discord and email destinations"
                        />
                    </FeatureSection>
                    <FeatureSection
                        id="keys"
                        kicker="AI keys"
                        title="Your keys, your costs"
                        body="Bring a key from Gemini, Anthropic, OpenAI, Groq, OpenRouter or Z.AI. Some providers have free tiers, and yours may charge beyond them. Keys are stored encrypted and shown only as their last four characters. Order several keys as a fallback chain if a provider is temporarily unavailable or out of quota."
                        flip
                    >
                        <ProductShot
                            name="ai-keys-mobile"
                            alt="AI key settings, with keys shown by their last four characters"
                        />
                    </FeatureSection>
                    <FeatureSection
                        id="security"
                        kicker="Account"
                        title="Security and privacy"
                        body="Protect your account with two-factor authentication and passkeys. Your profile and selected content are sent to your chosen AI provider to prepare your digests, under that provider’s terms. You can delete your account from settings."
                    >
                        <ProductShot
                            name="security-mobile"
                            alt="Security settings, with two-factor authentication and passkeys"
                        />
                    </FeatureSection>
                    <div
                        v-if="!page.props.auth.user && page.props.canRegister"
                        class="mt-4 rounded-3xl bg-primary/25 p-6 text-center md:p-10 dark:border dark:border-border dark:bg-card"
                    >
                        <h2 class="text-2xl font-semibold">
                            Ready for your first digest?
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
