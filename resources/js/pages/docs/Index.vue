<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import type { DocsSection } from '@/components/pulse/DocsNav.vue';
import type { DocsHeading } from '@/components/pulse/OnThisPage.vue';
import type { DocsEntry } from '@/components/pulse/PrevNext.vue';
import { Input } from '@/components/ui/input';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { show } from '@/routes/docs';

defineOptions({ layout: [] });
const props = defineProps<{
    sections: DocsSection[];
    searchIndex: (DocsEntry & { headings: DocsHeading[] })[];
}>();
const query = ref('');
onMounted(() => {
    if (window.location.hash === '#search') {
        document.getElementById('search')?.focus();
    }
});
const search = computed(() => query.value.trim().toLocaleLowerCase());
const results = computed(() =>
    props.searchIndex.filter((entry) =>
        `${entry.title} ${entry.summary} ${entry.sectionTitle} ${entry.headings.map((heading) => heading.text).join(' ')}`
            .toLocaleLowerCase()
            .includes(search.value),
    ),
);
</script>

<template>
    <PublicLayout>
        <Head title="Docs">
            <meta
                name="description"
                content="Short guides for every part of Pulse: interests, sources, AI keys, digests, delivery and reading."
            />
        </Head>
        <div class="mx-auto max-w-5xl px-4 py-10 md:py-14">
            <header class="mb-8 max-w-2xl">
                <h1 class="text-3xl font-semibold md:text-4xl">
                    Documentation
                </h1>
                <p class="mt-2 leading-relaxed text-muted-foreground">
                    Short guides for every part of Pulse, from choosing your
                    interests to reading your daily digest.
                </p>
                <div class="relative mt-5 max-w-md">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        id="search"
                        v-model="query"
                        type="search"
                        aria-label="Search the docs"
                        placeholder="Search the docs..."
                        class="scroll-mt-24 pl-9"
                    />
                </div>
            </header>
            <template v-if="search">
                <ul
                    v-if="results.length"
                    aria-label="Search results"
                    class="max-w-2xl divide-y divide-border rounded-2xl border border-border bg-card"
                >
                    <li v-for="entry in results" :key="entry.url">
                        <Link
                            :href="
                                show({
                                    section: entry.section,
                                    article: entry.slug,
                                })
                            "
                            class="block rounded-xl px-4 py-3 transition-colors hover:bg-muted"
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ entry.sectionTitle }}
                            </p>
                            <p class="font-semibold">{{ entry.title }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ entry.summary }}
                            </p>
                        </Link>
                    </li>
                </ul>
                <div
                    v-else
                    role="status"
                    class="max-w-2xl rounded-2xl border border-border bg-card p-8 text-center"
                >
                    <h2 class="text-xl font-semibold wrap-anywhere">
                        No results for “{{ query.trim() }}”
                    </h2>
                    <p class="mt-2 text-muted-foreground">
                        Try a different word, or clear your search to browse the
                        sections.
                    </p>
                </div>
            </template>
            <div v-else class="grid gap-4 sm:grid-cols-2">
                <section
                    v-for="section in sections"
                    :key="section.slug"
                    class="rounded-2xl border border-border bg-card p-5"
                >
                    <h2 class="text-lg font-semibold">{{ section.title }}</h2>
                    <ul class="mt-2 space-y-1.5">
                        <li
                            v-for="article in section.articles"
                            :key="article.slug"
                        >
                            <Link
                                :href="
                                    show({
                                        section: section.slug,
                                        article: article.slug,
                                    })
                                "
                                class="text-sm text-muted-foreground transition-colors hover:text-foreground hover:underline"
                                >{{ article.title }}</Link
                            >
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </PublicLayout>
</template>
