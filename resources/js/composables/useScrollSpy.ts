import { onMounted, onUnmounted, ref, toValue, watch } from 'vue';
import type { MaybeRefOrGetter } from 'vue';

export function useScrollSpy(ids: MaybeRefOrGetter<string[]>) {
    const active = ref(toValue(ids)[0]);
    let observer: IntersectionObserver | undefined;
    let stop: (() => void) | undefined;

    onMounted(() => {
        stop = watch(
            () => toValue(ids),
            (sectionIds) => {
                observer?.disconnect();
                active.value = sectionIds[0];
                if (typeof IntersectionObserver === 'undefined') return;

                observer = new IntersectionObserver(
                    (entries) => {
                        for (const entry of entries) {
                            if (entry.isIntersecting)
                                active.value = entry.target.id;
                        }
                    },
                    { rootMargin: '-15% 0px -70% 0px' },
                );
                for (const id of sectionIds) {
                    const element = document.getElementById(id);
                    if (element) observer.observe(element);
                }
            },
            { immediate: true, flush: 'post' },
        );
    });

    onUnmounted(() => {
        stop?.();
        observer?.disconnect();
    });

    return active;
}
