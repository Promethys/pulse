<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, PencilLine } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import DocsMenuButton from '@/components/pulse/DocsMenuButton.vue';
import DocsNav from '@/components/pulse/DocsNav.vue';
import type { DocsSection } from '@/components/pulse/DocsNav.vue';
import OnThisPage from '@/components/pulse/OnThisPage.vue';
import type { DocsHeading } from '@/components/pulse/OnThisPage.vue';
import OnThisPageCollapsible from '@/components/pulse/OnThisPageCollapsible.vue';
import PrevNext from '@/components/pulse/PrevNext.vue';
import type { DocsEntry } from '@/components/pulse/PrevNext.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { index } from '@/routes/docs';

defineOptions({ layout: [] });
const props = defineProps<{
    article: Omit<DocsEntry, 'url'> & { html: string; headings: DocsHeading[] };
    sections: DocsSection[];
    prev: DocsEntry | null;
    next: DocsEntry | null;
    editUrl: string;
}>();
const current = computed(() => ({
    section: props.article.section,
    article: props.article.slug,
}));
const content = ref<HTMLElement>();
let stop: (() => void) | undefined;

onMounted(() => {
    stop = watch(
        () => props.article.html,
        (_, __, onCleanup) => {
            const cleanups: (() => void)[] = [];
            for (const pre of content.value?.querySelectorAll('pre') ?? []) {
                const code = (
                    pre.querySelector('code')?.textContent ??
                    pre.textContent ??
                    ''
                ).replace(/\n$/, '');
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'docs-code-copy';
                button.textContent = 'Copy';
                button.setAttribute('aria-label', 'Copy code');
                let timer: ReturnType<typeof setTimeout> | undefined;
                let disposed = false;
                const copy = async () => {
                    try {
                        if (!navigator.clipboard) return;
                        await navigator.clipboard.writeText(code);
                        if (disposed) return;
                        button.textContent = 'Copied';
                        button.setAttribute('aria-label', 'Copied');
                        clearTimeout(timer);
                        timer = setTimeout(() => {
                            button.textContent = 'Copy';
                            button.setAttribute('aria-label', 'Copy code');
                        }, 1500);
                    } catch {
                        return;
                    }
                };
                button.addEventListener('click', copy);
                pre.append(button);
                cleanups.push(() => {
                    disposed = true;
                    clearTimeout(timer);
                    button.removeEventListener('click', copy);
                    button.remove();
                });
            }
            onCleanup(() => cleanups.forEach((cleanup) => cleanup()));
        },
        { immediate: true, flush: 'post' },
    );
});
onUnmounted(() => stop?.());
</script>

<template>
    <PublicLayout>
        <Head :title="`${article.title} - Docs`"
            ><meta name="description" :content="article.summary"
        /></Head>
        <div class="mx-auto max-w-6xl px-4 py-8 md:py-10">
            <div class="mb-5 lg:hidden">
                <DocsMenuButton :sections="sections" :current="current" />
            </div>
            <div class="flex gap-8">
                <aside
                    class="sticky top-24 hidden max-h-[calc(100vh-7rem)] w-56 shrink-0 self-start overflow-y-auto pb-8 lg:block"
                >
                    <DocsNav :sections="sections" :current="current" />
                </aside>
                <article class="max-w-2xl min-w-0 flex-1">
                    <nav aria-label="Breadcrumb" class="mb-4">
                        <ol
                            class="flex flex-wrap items-center gap-1 text-sm text-muted-foreground"
                        >
                            <li>
                                <Link
                                    :href="index()"
                                    class="focus-ring hover:text-foreground hover:underline active:text-foreground active:underline"
                                    >Docs</Link
                                >
                            </li>
                            <li aria-hidden="true">
                                <ChevronRight class="size-3.5" />
                            </li>
                            <li>{{ article.sectionTitle }}</li>
                            <li aria-hidden="true">
                                <ChevronRight class="size-3.5" />
                            </li>
                            <li
                                aria-current="page"
                                class="font-medium text-foreground"
                            >
                                {{ article.title }}
                            </li>
                        </ol>
                    </nav>
                    <h1 class="text-3xl font-semibold md:text-4xl">
                        {{ article.title }}
                    </h1>
                    <p class="mt-2 leading-relaxed text-muted-foreground">
                        {{ article.summary }}
                    </p>
                    <div class="mt-6">
                        <OnThisPageCollapsible :items="article.headings" />
                    </div>
                    <div
                        ref="content"
                        class="prose-pulse mt-2"
                        v-html="article.html"
                    />
                    <p class="mt-10">
                        <a
                            :href="editUrl"
                            class="inline-flex items-center gap-1.5 text-sm text-muted-foreground focus-ring hover:text-foreground hover:underline active:text-foreground active:underline"
                            ><PencilLine
                                class="size-3.5"
                                aria-hidden="true"
                            />Edit this page on GitHub</a
                        >
                    </p>
                    <PrevNext :prev="prev" :next="next" />
                </article>
                <OnThisPage :items="article.headings" />
            </div>
        </div>
    </PublicLayout>
</template>
