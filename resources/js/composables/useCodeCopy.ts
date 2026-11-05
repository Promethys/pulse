import { onMounted, onUnmounted, toValue, watch } from 'vue';
import type { MaybeRefOrGetter } from 'vue';

const COPIED_FOR = 1500;

function addCopyButton(pre: HTMLElement): () => void {
    const code = (
        pre.querySelector('code')?.textContent ??
        pre.textContent ??
        ''
    ).replace(/\n$/, '');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'code-copy';
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
            }, COPIED_FOR);
        } catch {
            return;
        }
    };

    button.addEventListener('click', copy);
    const wrapper = document.createElement('div');
    wrapper.className = 'code-block';
    pre.replaceWith(wrapper);
    wrapper.append(pre, button);

    return () => {
        disposed = true;
        clearTimeout(timer);
        button.removeEventListener('click', copy);
        button.remove();
        if (pre.parentNode === wrapper) wrapper.replaceWith(pre);
        else wrapper.remove();
    };
}

export function useCodeCopy(
    container: MaybeRefOrGetter<HTMLElement | null | undefined>,
    source?: MaybeRefOrGetter<string>,
) {
    let stop: (() => void) | undefined;

    onMounted(() => {
        stop = watch(
            () => toValue(source),
            (_, __, onCleanup) => {
                const cleanups = Array.from(
                    toValue(container)?.querySelectorAll('pre') ?? [],
                    addCopyButton,
                );
                onCleanup(() => cleanups.forEach((cleanup) => cleanup()));
            },
            { immediate: true, flush: 'post' },
        );
    });

    onUnmounted(() => stop?.());
}
