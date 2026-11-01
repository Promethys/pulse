<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PulseLogo from '@/components/pulse/PulseLogo.vue';
import { features, home, openSource, privacy, terms } from '@/routes';
import { index as docs } from '@/routes/docs';

const github = 'https://github.com/Promethys/pulse';
const productLinks = [
    { label: 'Features', href: features() },
    { label: 'Docs', href: docs() },
];
const projectLinks = [
    { label: 'GitHub', href: github },
    { label: 'Releases', href: `${github}/releases` },
    { label: 'Report a bug', href: `${github}/issues` },
];
const legalLinks = [
    { label: 'Privacy', href: privacy() },
    { label: 'Terms', href: terms() },
];
</script>

<template>
    <footer class="border-t border-border">
        <div
            class="mx-auto grid max-w-5xl gap-8 px-4 py-10 sm:grid-cols-2 md:grid-cols-4"
        >
            <div>
                <Link :href="home()"><PulseLogo /></Link>
                <p class="mt-3 max-w-xs text-sm text-muted-foreground">
                    One calm daily digest of the news you actually follow.
                </p>
            </div>
            <div>
                <p
                    class="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Product
                </p>
                <ul class="space-y-1.5 text-sm">
                    <li v-for="item in productLinks" :key="item.label">
                        <Link
                            :href="item.href"
                            class="text-muted-foreground transition-colors hover:text-foreground hover:underline"
                        >
                            {{ item.label }}
                        </Link>
                    </li>
                </ul>
            </div>
            <div>
                <p
                    class="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Project
                </p>
                <ul class="space-y-1.5 text-sm">
                    <li>
                        <Link
                            :href="openSource()"
                            class="text-muted-foreground transition-colors hover:text-foreground hover:underline"
                        >
                            Open source
                        </Link>
                    </li>
                    <li v-for="item in projectLinks" :key="item.label">
                        <a
                            :href="item.href"
                            target="_blank"
                            rel="noreferrer"
                            class="text-muted-foreground transition-colors hover:text-foreground hover:underline"
                        >
                            {{ item.label }}
                        </a>
                    </li>
                </ul>
            </div>
            <div>
                <p
                    class="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Legal
                </p>
                <ul class="space-y-1.5 text-sm">
                    <li v-for="item in legalLinks" :key="item.label">
                        <Link
                            :href="item.href"
                            class="text-muted-foreground transition-colors hover:text-foreground hover:underline"
                        >
                            {{ item.label }}
                        </Link>
                    </li>
                </ul>
            </div>
        </div>
        <p
            class="border-t border-border py-4 text-center text-xs text-muted-foreground"
        >
            Free and open source, MIT license. Find Pulse useful?
            <a
                :href="github"
                target="_blank"
                rel="noreferrer"
                class="underline underline-offset-2 hover:text-foreground"
                >Leave a star on GitHub</a
            >.
        </p>
    </footer>
</template>
