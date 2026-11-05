<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Bug, FolderGit2, Lightbulb, ScrollText, Terminal } from '@lucide/vue';
import { useTemplateRef } from 'vue';
import { Button } from '@/components/ui/button';
import { useCodeCopy } from '@/composables/useCodeCopy';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineOptions({ layout: [] });

const github = 'https://github.com/Promethys/pulse';
const commands = useTemplateRef<HTMLElement>('commands');
useCodeCopy(commands);
const contributions = [
    {
        icon: Bug,
        title: 'Report a bug',
        body: 'Open an issue with what you did, what you expected and what happened.',
        href: `${github}/issues`,
        label: 'Open an issue',
    },
    {
        icon: Lightbulb,
        title: 'Request a feature',
        body: 'Describe the problem it solves for you and how you would use it.',
        href: `${github}/issues`,
        label: 'Suggest a feature',
    },
    {
        icon: ScrollText,
        title: 'Contribute code',
        body: 'Read the contributing guide for setup, code style and checks before opening a pull request.',
        href: `${github}/blob/main/CONTRIBUTING.md`,
        label: 'Read the guide',
    },
];
</script>

<template>
    <PublicLayout>
        <Head title="Open source">
            <meta
                name="description"
                content="Pulse is free and open source under the MIT license. Read the code, run your own copy with Docker, report a bug or contribute."
            />
        </Head>
        <div class="mx-auto max-w-5xl px-4 py-10 md:py-14">
            <header class="mb-10">
                <h1 class="text-3xl font-semibold md:text-4xl">Open source</h1>
                <p class="mt-2 leading-relaxed text-muted-foreground">
                    Pulse is free and open source under the MIT license. Read
                    every line, run your own copy, and help shape it.
                </p>
                <Button class="mt-4 focus-ring" as-child>
                    <a :href="github" target="_blank" rel="noreferrer"
                        ><FolderGit2 aria-hidden="true" />Promethys/pulse on
                        GitHub</a
                    >
                </Button>
                <p class="mt-3 text-sm text-muted-foreground">
                    If you find Pulse useful, leave a star. It helps other
                    people find it.
                </p>
            </header>
            <section aria-labelledby="why">
                <h2 id="why" class="text-2xl font-semibold">Why open source</h2>
                <div
                    class="mt-3 space-y-3 leading-relaxed text-muted-foreground"
                >
                    <p>
                        Pulse reads your sources, stores your AI keys and uses
                        your profile to choose what matters to you. You should
                        be able to inspect how that works.
                    </p>
                    <p>
                        Read the code to check what is stored, what is sent to
                        your AI provider, and how account deletion works.
                    </p>
                </div>
            </section>
            <section class="mt-12" aria-labelledby="stack">
                <h2 id="stack" class="text-2xl font-semibold">The stack</h2>
                <p class="mt-3 leading-relaxed text-muted-foreground">
                    Laravel, Vue 3, Inertia and PostgreSQL. AI generation uses
                    Laravel’s provider-agnostic AI SDK.
                </p>
            </section>
            <section class="mt-12" aria-labelledby="run">
                <h2 id="run" class="text-2xl font-semibold">
                    Run your own copy
                </h2>
                <ol
                    class="mt-3 list-decimal space-y-2 pl-5 leading-relaxed text-muted-foreground"
                >
                    <li>
                        Clone the repository and copy the example environment
                        file.
                    </li>
                    <li>
                        Follow the Docker setup in the README to install
                        dependencies and initialize your local instance.
                    </li>
                    <li>
                        Open the app, set up your account and configure your
                        interests, AI keys and delivery.
                    </li>
                </ol>
                <div ref="commands" class="mt-4">
                    <pre
                        class="rounded-xl border border-border bg-muted p-4 text-sm break-all whitespace-pre-wrap"
                    ><code>git clone https://github.com/Promethys/pulse.git
cd pulse
cp .env.example .env</code></pre>
                </div>
                <p class="mt-3 text-sm text-muted-foreground">
                    Continue with the full setup in the
                    <a
                        :href="`${github}/blob/main/README.md`"
                        target="_blank"
                        rel="noreferrer"
                        class="text-primary-strong underline underline-offset-2 focus-ring hover:decoration-2 active:decoration-2"
                        >README</a
                    >, including scheduled digests and mail configuration.
                </p>
            </section>
            <section class="mt-12" aria-labelledby="contribute">
                <h2 id="contribute" class="text-2xl font-semibold">
                    Contribute
                </h2>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <div
                        v-for="item in contributions"
                        :key="item.title"
                        class="flex flex-col rounded-2xl border border-border bg-card p-5"
                    >
                        <component
                            :is="item.icon"
                            class="size-5 text-primary-strong"
                            aria-hidden="true"
                        />
                        <h3 class="mt-2 text-lg font-semibold">
                            {{ item.title }}
                        </h3>
                        <p
                            class="mt-1 flex-1 text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ item.body }}
                        </p>
                        <a
                            :href="item.href"
                            target="_blank"
                            rel="noreferrer"
                            class="mt-3 text-sm font-medium text-primary-strong focus-ring hover:underline active:underline"
                            >{{ item.label }}
                            <span aria-hidden="true">→</span></a
                        >
                    </div>
                </div>
            </section>
            <section
                class="mt-12 rounded-2xl border border-border bg-card p-5"
                aria-labelledby="license"
            >
                <h2
                    id="license"
                    class="flex items-center gap-2 text-lg font-semibold"
                >
                    <Terminal
                        class="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />MIT license
                </h2>
                <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                    Use, change and redistribute Pulse, including commercially,
                    while keeping the copyright and license notice. The software
                    is provided without warranty. Read the full terms in
                    <a
                        :href="`${github}/blob/main/LICENSE`"
                        target="_blank"
                        rel="noreferrer"
                        class="text-primary-strong underline underline-offset-2 focus-ring hover:decoration-2 active:decoration-2"
                        >LICENSE</a
                    >.
                </p>
            </section>
        </div>
    </PublicLayout>
</template>
