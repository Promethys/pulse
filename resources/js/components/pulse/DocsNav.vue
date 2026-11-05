<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { nextTick, onMounted, ref, watch } from 'vue';
import { cn } from '@/lib/utils';
import { show } from '@/routes/docs';

export interface DocsSection {
    slug: string;
    title: string;
    articles: { slug: string; title: string; summary: string }[];
}

export interface DocsCurrent {
    section: string;
    article: string;
}

const props = defineProps<{ sections: DocsSection[]; current?: DocsCurrent }>();
defineEmits<{ navigate: [] }>();
const nav = ref<HTMLElement>();
const revealCurrent = () =>
    nav.value
        ?.querySelector('[aria-current="page"]')
        ?.scrollIntoView?.({ block: 'nearest' });

onMounted(() => nextTick(revealCurrent));
watch(() => props.current, revealCurrent, { flush: 'post' });
</script>

<template>
    <nav ref="nav" aria-label="Documentation" class="space-y-5">
        <div v-for="section in sections" :key="section.slug">
            <p
                class="mb-1.5 px-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
                {{ section.title }}
            </p>
            <ul class="space-y-0.5">
                <li v-for="article in section.articles" :key="article.slug">
                    <Link
                        :href="
                            show({
                                section: section.slug,
                                article: article.slug,
                            })
                        "
                        :aria-current="
                            current?.section === section.slug &&
                            current.article === article.slug
                                ? 'page'
                                : undefined
                        "
                        :class="
                            cn(
                                'block rounded-lg px-3 py-1.5 text-sm transition-colors',
                                current?.section === section.slug &&
                                    current.article === article.slug
                                    ? 'bg-primary font-medium text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground active:text-foreground',
                            )
                        "
                        @click="$emit('navigate')"
                        class="focus-ring"
                        >{{ article.title }}</Link
                    >
                </li>
            </ul>
        </div>
    </nav>
</template>
