<script setup lang="ts">
defineProps<{
    items: { title: string; url: string; source: string | null }[];
}>();
</script>

<template>
    <section>
        <h2 class="text-lg font-semibold">From your sources so far</h2>
        <p v-if="!items.length" class="mt-3 text-sm text-muted-foreground">
            Collecting headlines from your sources. Refresh in a minute.
        </p>
        <ul v-else>
            <li
                v-for="item in items"
                :key="item.url"
                class="border-b border-border py-4 last:border-0"
            >
                <a
                    :href="item.url"
                    target="_blank"
                    rel="noopener"
                    class="font-sans text-[15px] leading-snug font-semibold focus-ring hover:underline"
                    >{{ item.title }}</a
                >
                <p
                    v-if="item.source"
                    class="mt-2 text-xs text-muted-foreground"
                >
                    {{ item.source }}
                </p>
            </li>
        </ul>
    </section>
</template>
