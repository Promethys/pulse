<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { FolderGit2, KeyRound, Send, Sparkles } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { login, register } from '@/routes';

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
</script>

<template>
    <Head title="Pulse: your daily news digest, from the interests you follow">
        <meta
            name="description"
            content="Pick your interests, bring your own AI key, and get one calm daily digest in the app, on Discord or by email. Open source."
        />
    </Head>
    <PublicLayout>
        <div class="mx-auto max-w-5xl px-4 pb-16">
            <section class="py-10 md:py-16">
                <h1
                    class="max-w-2xl text-4xl leading-tight font-semibold md:text-5xl"
                >
                    One calm daily digest of the news you actually follow.
                </h1>
                <p class="mt-4 max-w-xl text-muted-foreground">
                    Pulse reads your sources overnight and sends you a short,
                    grouped summary each morning. Free and open source. You
                    bring your own AI key.
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
                    <Button
                        v-if="!page.props.auth.user"
                        size="lg"
                        variant="outline"
                        as-child
                        ><Link :href="login()">Log in</Link></Button
                    >
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
            <section class="grid gap-4 md:grid-cols-3">
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
        </div>
    </PublicLayout>
</template>
