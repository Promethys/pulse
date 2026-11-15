<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { useScrollSpy } from '@/composables/useScrollSpy';
import { cn } from '@/lib/utils';

const props = defineProps<{ sections: { id: string; label: string }[] }>();
const active = useScrollSpy(() => props.sections.map((section) => section.id));
const nav = ref<HTMLElement>();
const availableHeight = ref<string>();
function revealActiveLink() {
    const element = nav.value;
    const link = element?.querySelector<HTMLElement>(
        '[aria-current="location"]',
    );
    if (!element?.clientHeight || !link) return;
    const top = link.offsetTop;
    const bottom = top + link.offsetHeight;
    if (top < element.scrollTop) {
        element.scrollTop = top;
    } else if (bottom > element.scrollTop + element.clientHeight) {
        element.scrollTop = bottom - element.clientHeight;
    }
}
watch([active, availableHeight], revealActiveLink, { flush: 'post' });
function resizeNav() {
    const element = nav.value;
    if (!element?.parentElement) return;
    const top = Number.parseFloat(getComputedStyle(element).top);
    if (!Number.isFinite(top)) return;
    const bottom = element.parentElement.getBoundingClientRect().bottom;
    availableHeight.value = `${Math.max(0, bottom - top)}px`;
}
onMounted(() => {
    resizeNav();
    window.addEventListener('scroll', resizeNav, { passive: true });
    window.addEventListener('resize', resizeNav);
});
onUnmounted(() => {
    window.removeEventListener('scroll', resizeNav);
    window.removeEventListener('resize', resizeNav);
});
</script>

<template>
    <nav
        ref="nav"
        aria-label="Sections"
        :style="
            availableHeight
                ? { maxHeight: `min(calc(100svh - 7rem), ${availableHeight})` }
                : undefined
        "
        class="hidden lg:sticky lg:top-24 lg:block lg:max-h-[calc(100svh-7rem)] lg:w-48 lg:shrink-0 lg:self-start lg:overflow-y-auto"
    >
        <ul class="space-y-1 border-l border-border">
            <li v-for="section in sections" :key="section.id">
                <a
                    :href="`#${section.id}`"
                    :aria-current="
                        active === section.id ? 'location' : undefined
                    "
                    :class="
                        cn(
                            '-ml-px block border-l-2 px-4 py-1 text-sm transition-colors',
                            active === section.id
                                ? 'border-primary-strong font-medium text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground active:text-foreground',
                        )
                    "
                    class="focus-ring"
                    >{{ section.label }}</a
                >
            </li>
        </ul>
    </nav>
</template>
