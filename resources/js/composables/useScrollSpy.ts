import { onMounted, onUnmounted, ref, toValue, watch } from 'vue';
import type { MaybeRefOrGetter } from 'vue';

const READING_LINE = 0.3;

export function useScrollSpy(ids: MaybeRefOrGetter<string[]>) {
    const active = ref(toValue(ids)[0]);
    let frame = 0;
    let stop: (() => void) | undefined;

    const update = () => {
        frame = 0;
        const sectionIds = toValue(ids);
        const { scrollHeight } = document.documentElement;
        const atBottom =
            scrollHeight > window.innerHeight &&
            window.scrollY + window.innerHeight >= scrollHeight - 2;
        const line = window.innerHeight * READING_LINE;
        const reached = sectionIds.findLast((id) => {
            const element = document.getElementById(id);

            return element && element.getBoundingClientRect().top <= line;
        });
        const linked = window.location.hash.slice(1);
        const bottom = sectionIds.includes(linked) ? linked : sectionIds.at(-1);
        active.value = (atBottom ? bottom : reached) ?? sectionIds[0];
    };
    const schedule = () => {
        frame ||= requestAnimationFrame(update);
    };

    onMounted(() => {
        stop = watch(() => toValue(ids), update, {
            immediate: true,
            flush: 'post',
        });
        window.addEventListener('scroll', schedule, { passive: true });
        window.addEventListener('resize', schedule);
        window.addEventListener('hashchange', schedule);
    });

    onUnmounted(() => {
        stop?.();
        cancelAnimationFrame(frame);
        window.removeEventListener('scroll', schedule);
        window.removeEventListener('resize', schedule);
        window.removeEventListener('hashchange', schedule);
    });

    return active;
}
