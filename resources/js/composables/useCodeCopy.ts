import { onMounted, onUnmounted, toValue, watch } from 'vue';
import type { MaybeRefOrGetter } from 'vue';

const COPIED_FOR = 1500;

function copyIcon(copied: boolean): SVGSVGElement {
    const namespace = 'http://www.w3.org/2000/svg';
    const icon = document.createElementNS(namespace, 'svg');
    icon.setAttribute('viewBox', '0 0 24 24');
    icon.setAttribute('width', '14');
    icon.setAttribute('height', '14');
    icon.setAttribute('stroke', 'currentColor');
    icon.setAttribute('fill', 'none');
    icon.setAttribute('stroke-width', '2');
    icon.setAttribute('stroke-linecap', 'round');
    icon.setAttribute('stroke-linejoin', 'round');
    icon.setAttribute('aria-hidden', 'true');

    if (!copied) {
        const rect = document.createElementNS(namespace, 'rect');
        rect.setAttribute('width', '14');
        rect.setAttribute('height', '14');
        rect.setAttribute('x', '8');
        rect.setAttribute('y', '8');
        rect.setAttribute('rx', '2');
        rect.setAttribute('ry', '2');
        icon.append(rect);
    }

    const path = document.createElementNS(namespace, 'path');
    path.setAttribute(
        'd',
        copied
            ? 'M20 6 9 17l-5-5'
            : 'M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2',
    );
    icon.append(path);

    return icon;
}

function addCopyButton(pre: HTMLElement): () => void {
    const code = (
        pre.querySelector('code')?.textContent ??
        pre.textContent ??
        ''
    ).replace(/\n$/, '');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'code-copy';
    const setCopied = (copied: boolean) => {
        const label = copied ? 'Copied' : 'Copy code';
        button.replaceChildren(copyIcon(copied));
        button.setAttribute('aria-label', label);
        button.dataset.tooltip = label;
    };
    setCopied(false);
    let timer: ReturnType<typeof setTimeout> | undefined;
    let disposed = false;

    const copy = async () => {
        try {
            if (!navigator.clipboard) return;
            await navigator.clipboard.writeText(code);
            if (disposed) return;
            setCopied(true);
            clearTimeout(timer);
            timer = setTimeout(() => {
                setCopied(false);
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
