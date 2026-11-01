<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, FolderGit2, KeyRound, Send, Sparkles } from '@lucide/vue';
import Faq from '@/components/pulse/Faq.vue';
import ProductShot from '@/components/pulse/ProductShot.vue';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { publicFaq } from '@/lib/publicSamples';
import { features, register } from '@/routes';
import { index as docs, show as article } from '@/routes/docs';

defineOptions({ layout: [] });

const page = usePage<{ canRegister: boolean }>();

const steps = [
    {
        icon: Sparkles,
        title: 'Pick your interests',
        body: 'AI, cooking, finance, football… choose what you follow. Tech interests also get a daily tip and topic.',
    },
    {
        icon: KeyRound,
        title: 'Add your AI key',
        body: 'Gemini, Anthropic, OpenAI, Groq, OpenRouter or Z.AI. Some providers have free tiers, and yours may charge beyond them.',
    },
    {
        icon: Send,
        title: 'Choose where to receive it',
        body: 'In the app, in as many Discord servers as you like, or by email.',
    },
];
const highlights = [
    {
        id: 'digest',
        title: 'A digest written for you',
        body: 'A short profile steers what the AI picks and how it summarizes it.',
    },
    {
        id: 'delivery',
        title: 'Delivered where you are',
        body: 'Always in the app. Optionally on Discord or by email, with news, learning, or both.',
    },
    {
        id: 'keys',
        title: 'Your keys, your costs',
        body: 'Your AI keys, stored encrypted. Order several keys as a fallback chain.',
    },
];
</script>

<template>
    <Head title="Your daily news digest, from the interests you follow">
        <meta
            name="description"
            content="Pick your interests, bring your own AI key, and get one calm daily digest in the app, on Discord or by email. Open source."
        />
    </Head>
    <PublicLayout>
        <div class="mx-auto max-w-5xl px-4 pb-16">
            <section class="py-12 md:py-20">
                <h1
                    class="max-w-2xl text-4xl leading-tight font-semibold md:text-5xl"
                >
                    One calm daily digest of the news you actually follow.
                </h1>
                <p class="mt-4 max-w-xl text-muted-foreground">
                    Pulse reads your sources and sends you a short, grouped
                    summary each day, at the time you choose. Free and open
                    source. You bring your own AI key.
                </p>
                <div class="mt-6 flex flex-wrap gap-2">
                    <Button
                        v-if="!page.props.auth.user && page.props.canRegister"
                        size="lg"
                        as-child
                        ><Link :href="register()"
                            >Create an account</Link
                        ></Button
                    >
                    <Button size="lg" variant="outline" as-child>
                        <Link :href="features()">Explore features</Link>
                    </Button>
                    <Button size="lg" variant="ghost" as-child>
                        <a
                            href="https://github.com/Promethys/pulse"
                            target="_blank"
                            rel="noreferrer"
                            ><FolderGit2 aria-hidden="true" /> Source code</a
                        >
                    </Button>
                </div>
            </section>
            <section aria-label="A day in Pulse">
                <p
                    class="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    What a day looks like
                </p>
                <div
                    class="overflow-hidden rounded-3xl border border-border bg-card"
                >
                    <ProductShot
                        name="today-desktop"
                        alt="A day in Pulse, with the tip, the topic and news grouped by topic"
                    />
                </div>
            </section>
            <section
                class="mt-14 grid gap-4 md:grid-cols-3"
                aria-label="How it works"
            >
                <div
                    v-for="(step, index) in steps"
                    :key="step.title"
                    class="rounded-2xl border border-border bg-card p-5"
                >
                    <div
                        class="flex items-center gap-2 text-xs font-semibold tracking-wider text-primary-strong uppercase"
                    >
                        <component
                            :is="step.icon"
                            class="size-4"
                            aria-hidden="true"
                        />
                        Step {{ index + 1 }}
                    </div>
                    <h2 class="mt-2 text-lg font-semibold">{{ step.title }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ step.body }}
                    </p>
                </div>
            </section>
            <section
                class="mt-14 grid gap-4 md:grid-cols-3"
                aria-label="Highlights"
            >
                <Link
                    v-for="highlight in highlights"
                    :key="highlight.id"
                    :href="`${features.url()}#${highlight.id}`"
                    class="group rounded-2xl border border-border bg-card p-5 transition-colors hover:bg-secondary"
                >
                    <h2 class="flex items-center gap-1 text-lg font-semibold">
                        {{ highlight.title }}
                        <ArrowRight
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </h2>
                    <p
                        class="mt-1 text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ highlight.body }}
                    </p>
                </Link>
            </section>
            <section
                class="mt-14 rounded-3xl border border-border bg-card p-6 md:p-10"
                aria-label="What it costs"
            >
                <p
                    class="text-xs font-semibold tracking-wider text-primary-strong uppercase"
                >
                    What it costs
                </p>
                <h2 class="mt-1 text-2xl font-semibold md:text-3xl">
                    Free. You bring your key.
                </h2>
                <div
                    class="mt-3 max-w-2xl space-y-3 leading-relaxed text-muted-foreground"
                >
                    <p>
                        Pulse is free and open source. Your chosen AI provider
                        writes the summaries using your key.
                    </p>
                    <p>
                        Some providers have free tiers, and yours may charge
                        beyond them. Check usage and billing directly with your
                        provider.
                    </p>
                </div>
                <Button variant="outline" class="mt-5" as-child>
                    <Link
                        :href="
                            article({
                                section: 'ai-keys',
                                article: 'what-it-costs',
                            })
                        "
                        >Read: what it costs</Link
                    >
                </Button>
            </section>
            <section
                class="mt-14 max-w-2xl"
                aria-label="Frequently asked questions"
            >
                <h2 class="text-2xl font-semibold md:text-3xl">Questions</h2>
                <Faq :items="publicFaq" class="mt-4" />
            </section>
            <section
                class="mt-14 rounded-3xl bg-primary/25 p-6 text-center md:p-12 dark:border dark:border-border dark:bg-card"
                aria-label="Get started"
            >
                <h2 class="text-2xl font-semibold md:text-3xl">
                    Read less, know more.
                </h2>
                <p class="mx-auto mt-2 max-w-md text-muted-foreground">
                    Choose your interests, add an AI key, and decide where to
                    receive your digest.
                </p>
                <div class="mt-5 flex flex-wrap justify-center gap-2">
                    <Button
                        v-if="!page.props.auth.user && page.props.canRegister"
                        size="lg"
                        as-child
                    >
                        <Link :href="register()">Create an account</Link>
                    </Button>
                    <Button size="lg" variant="outline" as-child
                        ><Link :href="docs()">Read the docs</Link></Button
                    >
                </div>
            </section>
        </div>
    </PublicLayout>
</template>
